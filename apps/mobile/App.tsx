import { QueryClient, QueryClientProvider, useMutation, useQuery } from '@tanstack/react-query';
import * as ImagePicker from 'expo-image-picker';
import { StatusBar } from 'expo-status-bar';
import { createElement, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Image as RNImage,
  KeyboardAvoidingView,
  Linking,
  Modal,
  Platform,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import type { NativeSyntheticEvent, TextInputKeyPressEventData } from 'react-native';
import { z } from 'zod';

const queryClient = new QueryClient();
const apiBaseUrl = normalizeApiBaseUrl(process.env.EXPO_PUBLIC_API_URL);
const authTokenStorageKey = 'machine-error-helper.auth-token';
const selectedMachineStoragePrefix = 'machine-error-helper.selected-machine';
const cloudInputRange = [0, 0.25, 0.5, 0.75, 1];
const cloudPhaseFrames = [0, 1.35, 2.7, 4.05, 0];
const cloudAnimationDurationMs = 76000;
const cloudPhaseSpeed = 0.000032;

type DotTone = 'ghost' | 'dim' | 'mid' | 'bright' | 'cyan' | 'blue' | 'violet' | 'aqua';
type DotCell = {
  key: string;
  tone: DotTone;
  opacityFrames: number[];
};
type DotMeshSpec = {
  cellSize: number;
  height: number;
  rows: DotCell[][];
  width: number;
};
type CanvasDot = {
  accentStrength: number;
  column: number;
  row: number;
  tone: DotTone;
  x: number;
  xNorm: number;
  y: number;
  yNorm: number;
};
type CanvasDotSpec = {
  cellSize: number;
  dots: CanvasDot[];
  height: number;
  width: number;
};

function normalizeApiBaseUrl(value: string | undefined): string {
  const trimmed = value?.trim().replace(/\/+$/, '');

  if (trimmed) {
    return trimmed;
  }

  return Platform.OS === 'web' ? '/api' : 'http://localhost:8090/api';
}

function seededNoise(row: number, column: number, phase: number): number {
  const value = Math.sin(column * 12.9898 + row * 78.233 + phase * 37.719) * 43758.5453;

  return value - Math.floor(value);
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

function smoothstep(edge0: number, edge1: number, value: number): number {
  const t = clamp((value - edge0) / (edge1 - edge0), 0, 1);

  return t * t * (3 - 2 * t);
}

function cloudNoise(x: number, y: number, row: number, column: number, phase: number): number {
  const diagonalA = Math.sin((x * 3.4 - y * 4.9 + phase * 1.15) * Math.PI);
  const diagonalB = Math.sin((x * 5.1 + y * 2.6 - phase * 0.9 + 0.65) * Math.PI);
  const broadCloud = Math.sin((x * 1.8 + y * 2.2 + phase * 0.65 + 1.7) * Math.PI);
  const fineCloud = Math.sin((x * 6.2 - y * 5.8 + phase * 1.2) * Math.PI) * 0.08;
  const grain = (seededNoise(row, column, Math.floor(phase * 0.5)) - 0.5) * 0.08;

  return (diagonalA * 0.36 + diagonalB * 0.28 + broadCloud * 0.28 + fineCloud + grain + 1) / 2;
}

function dotOpacityAt(x: number, y: number, row: number, column: number, phase: number): number {
  const cloud = smoothstep(0.46, 0.86, cloudNoise(x, y, row, column, phase));
  const darkCore = Math.exp(-(((x - 0.48) / 0.22) ** 2 + ((y - 0.52) / 0.3) ** 2));
  const puddles = colorPuddlesAt(x, y);
  const puddleGlow = Math.max(puddles.cyan, puddles.blue, puddles.violet, puddles.aqua);
  const edgeX = smoothstep(0.02, 0.18, x) * (1 - smoothstep(0.82, 0.99, x));
  const edgeY = smoothstep(0.02, 0.16, y) * (1 - smoothstep(0.86, 0.99, y));
  const edgeCalm = clamp(edgeX * edgeY, 0, 1);
  const shapedCloud = cloud * (1 - darkCore * 0.86) * (0.18 + edgeCalm * 0.82);

  return clamp(0.035 + shapedCloud * 0.86 + puddleGlow * 0.13 * edgeCalm, 0.025, 0.94);
}

function colorPuddlesAt(x: number, y: number): { aqua: number; blue: number; cyan: number; violet: number } {
  return {
    aqua: Math.exp(-(((x - 0.18) / 0.18) ** 2 + ((y - 0.78) / 0.16) ** 2)),
    blue: Math.max(
      Math.exp(-(((x - 0.24) / 0.18) ** 2 + ((y - 0.28) / 0.16) ** 2)),
      Math.exp(-(((x - 0.66) / 0.16) ** 2 + ((y - 0.72) / 0.2) ** 2)) * 0.75,
    ),
    cyan: Math.max(
      Math.exp(-(((x - 0.86) / 0.14) ** 2 + ((y - 0.4) / 0.16) ** 2)),
      Math.exp(-(((x - 0.5) / 0.12) ** 2 + ((y - 0.2) / 0.14) ** 2)) * 0.68,
    ),
    violet: Math.exp(-(((x - 0.7) / 0.16) ** 2 + ((y - 0.18) / 0.16) ** 2)) * 0.72,
  };
}

function dominantPuddleTone(puddles: ReturnType<typeof colorPuddlesAt>): { strength: number; tone: DotTone } {
  const entries: Array<{ strength: number; tone: DotTone }> = [
    { strength: puddles.cyan, tone: 'cyan' },
    { strength: puddles.blue, tone: 'blue' },
    { strength: puddles.violet, tone: 'violet' },
    { strength: puddles.aqua, tone: 'aqua' },
  ];

  return entries.reduce((best, entry) => (entry.strength > best.strength ? entry : best), { strength: 0, tone: 'ghost' as DotTone });
}

function lerp(start: number, end: number, amount: number): number {
  return start + (end - start) * amount;
}

function dotCellSizeForViewport(width: number): number {
  if (width < 480) {
    return 5;
  }

  if (width < 900) {
    return 6;
  }

  if (width < 1400) {
    return 7;
  }

  return 8;
}

function buildDotMesh(columns: number, rows: number): DotCell[][] {
  return Array.from({ length: rows }, (_, rowIndex) => {
    const y = rows <= 1 ? 0 : rowIndex / (rows - 1);

    return Array.from({ length: columns }, (_, columnIndex) => {
      const x = columns <= 1 ? 0 : columnIndex / (columns - 1);
      const accent = dominantPuddleTone(colorPuddlesAt(x, y));
      const opacityFrames = cloudPhaseFrames.map((phase) => dotOpacityAt(x, y, rowIndex, columnIndex, phase));
      const peakOpacity = Math.max(...opacityFrames);
      let tone: DotTone = 'ghost';

      if (accent.strength > 0.38 && peakOpacity > 0.32) {
        tone = accent.tone;
      } else if (peakOpacity > 0.82) {
        tone = 'bright';
      } else if (peakOpacity > 0.58) {
        tone = 'mid';
      } else if (peakOpacity > 0.28) {
        tone = 'dim';
      }

      return {
        key: `${rowIndex}-${columnIndex}`,
        opacityFrames,
        tone,
      };
    });
  });
}

function buildDotMeshSpec(viewportWidth: number, viewportHeight: number): DotMeshSpec {
  const cellSize = dotCellSizeForViewport(viewportWidth);
  const overscan = cellSize * 10;
  const columns = Math.ceil((viewportWidth + overscan * 2) / cellSize);
  const rows = Math.ceil((viewportHeight + overscan * 2) / cellSize);

  return {
    cellSize,
    height: rows * cellSize,
    rows: buildDotMesh(columns, rows),
    width: columns * cellSize,
  };
}

function buildCanvasDotSpec(viewportWidth: number, viewportHeight: number): CanvasDotSpec {
  const cellSize = dotCellSizeForViewport(viewportWidth);
  const overscan = cellSize * 10;
  const columns = Math.ceil((viewportWidth + overscan * 2) / cellSize);
  const rows = Math.ceil((viewportHeight + overscan * 2) / cellSize);
  const dots: CanvasDot[] = [];

  for (let row = 0; row < rows; row += 1) {
    const yNorm = rows <= 1 ? 0 : row / (rows - 1);

    for (let column = 0; column < columns; column += 1) {
      const xNorm = columns <= 1 ? 0 : column / (columns - 1);
      const accent = dominantPuddleTone(colorPuddlesAt(xNorm, yNorm));

      dots.push({
        accentStrength: accent.strength,
        column,
        row,
        tone: accent.tone,
        x: column * cellSize + cellSize / 2,
        xNorm,
        y: row * cellSize + cellSize / 2,
        yNorm,
      });
    }
  }

  return {
    cellSize,
    dots,
    height: rows * cellSize,
    width: columns * cellSize,
  };
}

class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
  }
}

const authUserSchema = z.object({
  id: z.number(),
  name: z.string(),
  email: z.string().email(),
});

const loginResponseSchema = z.object({
  data: z.object({
    token: z.string(),
    user: authUserSchema,
  }),
});

const meResponseSchema = z.object({
  data: z.object({
    user: authUserSchema,
  }),
});

const dashboardColorMeaningSchema = z
  .object({
    id: z.number(),
    label: z.string(),
    ai_key: z.string().nullable().optional(),
    hex_color: z.string(),
    description: z.string().nullable().optional(),
    priority: z.number().nullable().optional(),
  })
  .passthrough();

const machineSchema = z.object({
  id: z.number(),
  name: z.string(),
  slug: z.string(),
  manufacturer: z.string().nullable(),
  model_number: z.string().nullable(),
  dashboard_color_meanings: z.array(dashboardColorMeaningSchema).optional().default([]),
}).passthrough();

const machinesResponseSchema = z.object({
  data: z.array(machineSchema),
});

const machineResponseSchema = z.object({
  data: machineSchema,
});

const diagnosisCreateSchema = z.object({
  id: z.string(),
  status: z.string(),
  poll_url: z.string(),
});

const codeDocumentationSchema = z
  .object({
    id: z.number(),
    title: z.string(),
    content: z.unknown().nullable(),
  })
  .passthrough();

const diagnosticEntrySchema = z
  .object({
    id: z.number(),
    module_key: z.string().nullable().optional(),
    primary_code: z.string().nullable().optional(),
    title: z.string().nullable().optional(),
    meaning: z.string().nullable().optional(),
    cause: z.string().nullable().optional(),
    recommended_action: z.string().nullable().optional(),
    severity: z.string().nullable().optional(),
    source_page_number: z.number().nullable().optional(),
    confidence: z.number().nullable().optional(),
    code_documentations: z.array(codeDocumentationSchema).optional().default([]),
  })
  .passthrough();

const diagnosisCandidateSchema = z
  .object({
    id: z.number(),
    candidate_code: z.string().nullable(),
    normalized_code: z.string().nullable(),
    source: z.string().nullable().optional(),
    confidence: z.number().nullable().optional(),
    metadata: z.record(z.string(), z.unknown()).nullable().optional(),
    matched_diagnostic_entry: diagnosticEntrySchema.nullable().optional(),
    dashboard_color_meaning: dashboardColorMeaningSchema.nullable().optional(),
  })
  .passthrough();

const diagnosisDetailSchema = z.object({
  data: z
    .object({
      id: z.string(),
      status: z.string(),
      machine: machineSchema.nullable(),
      confidence: z.number().nullable(),
      candidates: z.array(diagnosisCandidateSchema).nullable(),
      selected_diagnostic_entry: diagnosticEntrySchema.nullable(),
      result: z.record(z.string(), z.unknown()).nullable(),
      screenshot_url: z.string().nullable(),
    })
    .passthrough(),
});

const diagnosisHistoryItemSchema = z
  .object({
    id: z.string(),
    status: z.string(),
    created_at: z.string().nullable(),
    confidence: z.number().nullable(),
    machine: machineSchema.nullable(),
    selected_diagnostic_entry: diagnosticEntrySchema.nullable(),
    ai_detected_codes: z.array(z.string()),
    user_entered_codes: z.array(z.string()),
    candidates: z.array(diagnosisCandidateSchema).optional().default([]),
    screenshot_url: z.string().nullable(),
  })
  .passthrough();

const diagnosisHistoryResponseSchema = z.object({
  data: z.array(diagnosisHistoryItemSchema),
});

type Machine = z.infer<typeof machineSchema>;
type DiagnosisCreate = z.infer<typeof diagnosisCreateSchema>;
type DiagnosisDetail = z.infer<typeof diagnosisDetailSchema>['data'];
type DiagnosisHistoryItem = z.infer<typeof diagnosisHistoryItemSchema>;
type DiagnosisCandidate = z.infer<typeof diagnosisCandidateSchema>;
type DiagnosticEntry = z.infer<typeof diagnosticEntrySchema>;
type CodeDocumentation = z.infer<typeof codeDocumentationSchema>;
type DashboardColorMeaning = z.infer<typeof dashboardColorMeaningSchema>;
type AuthUser = z.infer<typeof authUserSchema>;
type ImageSource = 'camera' | 'library';
type ScreenshotPreviewSource = {
  height?: number | null;
  uri: string;
  width?: number | null;
};
type HistoryCodeChip = {
  code: string;
  colorMeaning: DashboardColorMeaning | null;
};
type AppPage = 'dashboard' | 'machines' | 'diagnosis' | 'history' | 'history-detail';
type DiagnosisStep = 'upload' | 'confirm' | 'result';
type ImageQualityStatus = 'pass' | 'warn' | 'fail';
type ImageQuality = {
  status: ImageQualityStatus;
  sharpness: number | null;
  messages: string[];
};
type ConfirmCodeRow = {
  code: string;
  colorMeaningId: number | null;
  id: string;
};

function sortMachinesByName(machines: Machine[]): Machine[] {
  return [...machines].sort((first, second) => first.name.localeCompare(second.name));
}

const diagnosisSteps: Array<{ id: DiagnosisStep; title: string }> = [
  { id: 'upload', title: 'Screenshot' },
  { id: 'confirm', title: 'Confirm codes' },
  { id: 'result', title: 'Result' },
];

async function parseJsonResponse<T>(response: Response, schema: z.ZodType<T>, fallbackMessage: string): Promise<T> {
  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const message = typeof payload?.message === 'string' ? payload.message : fallbackMessage;

    throw new ApiError(message, response.status);
  }

  return schema.parse(payload);
}

function authHeaders(token: string): Record<string, string> {
  return {
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  };
}

function resolveAssetUrl(url: string): string {
  const apiOrigin = apiBaseUrl.replace(/\/api\/?$/, '');
  const malformedLocalStorageUrl = url.match(/^https?:\/+(storage\/.*)$/i);

  if (malformedLocalStorageUrl) {
    return new URL(`/${malformedLocalStorageUrl[1]}`, `${apiOrigin}/`).toString();
  }

  if (/^(?:https?:\/\/|blob:|data:|file:)/i.test(url)) {
    return url;
  }

  return new URL(url, `${apiOrigin}/`).toString();
}

function getBrowserStorage(): Storage | null {
  try {
    return typeof globalThis.localStorage === 'undefined' ? null : globalThis.localStorage;
  } catch {
    return null;
  }
}

async function readStoredAuthToken(): Promise<string | null> {
  return getBrowserStorage()?.getItem(authTokenStorageKey) ?? null;
}

async function storeAuthToken(token: string): Promise<void> {
  getBrowserStorage()?.setItem(authTokenStorageKey, token);
}

async function forgetAuthToken(): Promise<void> {
  getBrowserStorage()?.removeItem(authTokenStorageKey);
}

function selectedMachineStorageKey(userId: number): string {
  return `${selectedMachineStoragePrefix}.${userId}`;
}

function readStoredMachineId(userId: number): number | null {
  const value = getBrowserStorage()?.getItem(selectedMachineStorageKey(userId));
  const parsed = value ? Number(value) : Number.NaN;

  return Number.isInteger(parsed) ? parsed : null;
}

function storeSelectedMachineId(userId: number, machineId: number): void {
  getBrowserStorage()?.setItem(selectedMachineStorageKey(userId), String(machineId));
}

function forgetSelectedMachineId(userId: number): void {
  getBrowserStorage()?.removeItem(selectedMachineStorageKey(userId));
}

async function loginToApi(input: { email: string; password: string }): Promise<{ token: string; user: AuthUser }> {
  const response = await fetch(`${apiBaseUrl}/login`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      email: input.email,
      password: input.password,
      device_name: Platform.OS === 'web' ? 'web-app' : `${Platform.OS}-app`,
    }),
  });

  const payload = await parseJsonResponse(response, loginResponseSchema, 'Could not log in.');

  return payload.data;
}

async function registerWithApi(input: { name: string; email: string; password: string; passwordConfirmation: string }): Promise<{ token: string; user: AuthUser }> {
  const response = await fetch(`${apiBaseUrl}/register`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      name: input.name,
      email: input.email,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
      device_name: Platform.OS === 'web' ? 'web-app' : `${Platform.OS}-app`,
    }),
  });

  const payload = await parseJsonResponse(response, loginResponseSchema, 'Could not create account.');

  return payload.data;
}

async function fetchCurrentUser(token: string): Promise<AuthUser> {
  const response = await fetch(`${apiBaseUrl}/me`, {
    headers: authHeaders(token),
  });
  const payload = await parseJsonResponse(response, meResponseSchema, 'Could not load user.');

  return payload.data.user;
}

async function logoutFromApi(token: string): Promise<void> {
  await fetch(`${apiBaseUrl}/logout`, {
    method: 'POST',
    headers: authHeaders(token),
  }).catch(() => null);
}

async function fetchMachines(token: string, search: string): Promise<Machine[]> {
  const response = await fetch(`${apiBaseUrl}/machines?search=${encodeURIComponent(search)}`, {
    headers: authHeaders(token),
  });
  const payload = await parseJsonResponse(response, machinesResponseSchema, 'Could not load machines.');

  return payload.data;
}

async function fetchUserMachines(token: string): Promise<Machine[]> {
  const response = await fetch(`${apiBaseUrl}/user/machines`, {
    headers: authHeaders(token),
  });
  const payload = await parseJsonResponse(response, machinesResponseSchema, 'Could not load your machines.');

  return payload.data;
}

async function attachUserMachine(input: { machineId: number; token: string }): Promise<Machine> {
  const response = await fetch(`${apiBaseUrl}/user/machines/${input.machineId}`, {
    method: 'POST',
    headers: authHeaders(input.token),
  });
  const payload = await parseJsonResponse(response, machineResponseSchema, 'Could not add machine.');

  return payload.data;
}

async function detachUserMachine(input: { machineId: number; token: string }): Promise<void> {
  const response = await fetch(`${apiBaseUrl}/user/machines/${input.machineId}`, {
    method: 'DELETE',
    headers: authHeaders(input.token),
  });

  await parseJsonResponse(response, z.unknown(), 'Could not remove machine.');
}

async function fetchDiagnosis(id: string, token: string): Promise<DiagnosisDetail> {
  const response = await fetch(`${apiBaseUrl}/diagnoses/${id}`, {
    headers: authHeaders(token),
  });
  const payload = await parseJsonResponse(response, diagnosisDetailSchema, 'Could not load diagnosis.');

  return payload.data;
}

async function fetchDiagnosisHistory(token: string): Promise<DiagnosisHistoryItem[]> {
  const response = await fetch(`${apiBaseUrl}/diagnoses/history`, {
    headers: authHeaders(token),
  });
  const payload = await parseJsonResponse(response, diagnosisHistoryResponseSchema, 'Could not load scan history.');

  return payload.data;
}

async function pickImage(source: ImageSource): Promise<ImagePicker.ImagePickerAsset | null> {
  const result =
    source === 'camera'
      ? await ImagePicker.launchCameraAsync({
          mediaTypes: ['images'],
          quality: 0.9,
        })
      : await ImagePicker.launchImageLibraryAsync({
          mediaTypes: ['images'],
          quality: 0.9,
        });

  return result.canceled ? null : result.assets[0];
}

async function appendScreenshot(formData: FormData, asset: ImagePicker.ImagePickerAsset): Promise<void> {
  if (Platform.OS === 'web') {
    const response = await fetch(asset.uri);
    const blob = await response.blob();

    formData.append('screenshot', blob, asset.fileName ?? 'machine-dashboard.jpg');

    return;
  }

  formData.append('screenshot', {
    uri: asset.uri,
    name: asset.fileName ?? 'machine-dashboard.jpg',
    type: asset.mimeType ?? 'image/jpeg',
  } as unknown as Blob);
}

async function uploadDiagnosis(input: { machineId: number; asset: ImagePicker.ImagePickerAsset; token: string }): Promise<DiagnosisCreate> {
  const formData = new FormData();

  formData.append('machine_id', String(input.machineId));
  await appendScreenshot(formData, input.asset);

  const response = await fetch(`${apiBaseUrl}/diagnoses`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${input.token}`,
    },
    body: formData,
  });

  return parseJsonResponse(response, diagnosisCreateSchema, 'Could not upload screenshot.');
}

async function submitManualCode(input: { diagnosisId: string; entries: Array<{ code: string; dashboard_color_meaning_id: number | null }>; moduleKey: string; token: string }): Promise<DiagnosisDetail> {
  const response = await fetch(`${apiBaseUrl}/diagnoses/${input.diagnosisId}/manual-code`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${input.token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      entries: input.entries,
      module_key: input.moduleKey || undefined,
    }),
  });

  const payload = await parseJsonResponse(response, diagnosisDetailSchema, 'Could not match manual codes.');

  return payload.data;
}

async function validateImageQuality(asset: ImagePicker.ImagePickerAsset): Promise<ImageQuality> {
  const messages: string[] = [];
  const width = asset.width ?? 0;
  const height = asset.height ?? 0;
  let status: ImageQualityStatus = 'pass';
  let sharpness: number | null = null;

  if (width < 900 || height < 600) {
    status = 'fail';
    messages.push('Image resolution is too low for reliable code reading.');
  } else if (width < 1280 || height < 720) {
    status = 'warn';
    messages.push('Image is usable, but a larger photo would be safer.');
  }

  if (Platform.OS === 'web') {
    sharpness = await estimateSharpnessWeb(asset.uri).catch(() => null);

    if (sharpness !== null && sharpness < 8) {
      status = 'fail';
      messages.push('Image looks too blurred. Retake it with the display in focus.');
    } else if (sharpness !== null && sharpness < 18 && status !== 'fail') {
      status = 'warn';
      messages.push('Image is a bit soft. Check that the extracted codes are correct.');
    }
  } else {
    messages.push('Mobile blur detection is limited here. Keep the display sharp and fill the frame.');
  }

  if (messages.length === 0) {
    messages.push('Image quality looks good for automatic extraction.');
  }

  return { status, sharpness, messages };
}

async function estimateSharpnessWeb(uri: string): Promise<number> {
  if (typeof document === 'undefined') {
    return 0;
  }

  const image = document.createElement('img');
  image.decoding = 'async';
  image.src = uri;

  await new Promise<void>((resolve, reject) => {
    image.onload = () => resolve();
    image.onerror = () => reject(new Error('Could not read image for quality validation.'));
  });

  const canvas = document.createElement('canvas');
  const targetWidth = 220;
  const targetHeight = Math.max(1, Math.round((image.naturalHeight / image.naturalWidth) * targetWidth));
  canvas.width = targetWidth;
  canvas.height = targetHeight;

  const context = canvas.getContext('2d');

  if (!context) {
    return 0;
  }

  context.drawImage(image, 0, 0, targetWidth, targetHeight);

  const pixels = context.getImageData(0, 0, targetWidth, targetHeight).data;
  const edges: number[] = [];

  for (let y = 1; y < targetHeight - 1; y++) {
    for (let x = 1; x < targetWidth - 1; x++) {
      const center = grayscaleAt(pixels, targetWidth, x, y) * 4;
      const value =
        center -
        grayscaleAt(pixels, targetWidth, x - 1, y) -
        grayscaleAt(pixels, targetWidth, x + 1, y) -
        grayscaleAt(pixels, targetWidth, x, y - 1) -
        grayscaleAt(pixels, targetWidth, x, y + 1);
      edges.push(value);
    }
  }

  const mean = edges.reduce((sum, value) => sum + value, 0) / Math.max(edges.length, 1);
  const variance = edges.reduce((sum, value) => sum + (value - mean) ** 2, 0) / Math.max(edges.length, 1);

  return Math.round(Math.sqrt(variance) * 10) / 10;
}

function grayscaleAt(pixels: Uint8ClampedArray, width: number, x: number, y: number): number {
  const offset = (y * width + x) * 4;

  return pixels[offset] * 0.299 + pixels[offset + 1] * 0.587 + pixels[offset + 2] * 0.114;
}

function AmbientBackground() {
  const { height, width } = useWindowDimensions();
  const cloud = useRef(new Animated.Value(0)).current;
  const mesh = useMemo(() => buildDotMeshSpec(width, height), [height, width]);

  useEffect(() => {
    if (Platform.OS === 'web') {
      return undefined;
    }

    const cloudLoop = Animated.loop(
      Animated.timing(cloud, {
        toValue: 1,
        duration: cloudAnimationDurationMs,
        easing: Easing.linear,
        useNativeDriver: true,
      }),
    );

    cloudLoop.start();

    return () => {
      cloudLoop.stop();
    };
  }, [cloud]);

  return (
    <View pointerEvents="none" style={styles.ambientRoot}>
      <View style={styles.backgroundBase} />
      {Platform.OS === 'web' ? (
        <WebHalftoneCanvas viewportHeight={height} viewportWidth={width} />
      ) : (
        <View style={[styles.halftoneLayer, { height: mesh.height, left: -mesh.cellSize * 10, top: -mesh.cellSize * 10, width: mesh.width }]}>
          <DotMesh cellSize={mesh.cellSize} phase={cloud} rows={mesh.rows} />
        </View>
      )}
      <View style={styles.halftoneVignette} />
    </View>
  );
}

function ScreenshotViewer({ source }: { source: ScreenshotPreviewSource }) {
  const { height: viewportHeight, width: viewportWidth } = useWindowDimensions();
  const [viewerOpen, setViewerOpen] = useState(false);
  const [zoom, setZoom] = useState(1);
  const [imageSize, setImageSize] = useState(() => {
    if (source.width && source.height) {
      return {
        height: source.height,
        width: source.width,
      };
    }

    return null;
  });

  useEffect(() => {
    let cancelled = false;

    setZoom(1);

    if (source.width && source.height) {
      setImageSize({
        height: source.height,
        width: source.width,
      });

      return () => {
        cancelled = true;
      };
    }

    setImageSize(null);
    RNImage.getSize(
      source.uri,
      (width, height) => {
        if (!cancelled) {
          setImageSize({ height, width });
        }
      },
      () => {
        if (!cancelled) {
          setImageSize({ height: 3, width: 4 });
        }
      },
    );

    return () => {
      cancelled = true;
    };
  }, [source.height, source.uri, source.width]);

  const aspectRatio = imageSize ? imageSize.width / imageSize.height : 4 / 3;
  const previewWidth = Math.min(Math.max(viewportWidth - 84, 220), 720);
  const previewHeight = clamp(previewWidth / aspectRatio, 180, 320);
  const modalViewportWidth = Math.max(220, viewportWidth - (viewportWidth < 640 ? 34 : 52));
  const modalViewportHeight = Math.max(240, viewportHeight - 198);
  const fittedScale = imageSize
    ? Math.min(modalViewportWidth / imageSize.width, modalViewportHeight / imageSize.height)
    : 1;
  const baseImageWidth = imageSize ? Math.max(180, imageSize.width * fittedScale) : modalViewportWidth;
  const baseImageHeight = imageSize ? Math.max(140, imageSize.height * fittedScale) : modalViewportHeight * 0.75;
  const zoomedWidth = baseImageWidth * zoom;
  const zoomedHeight = baseImageHeight * zoom;

  function adjustZoom(nextZoom: number): void {
    setZoom(clamp(nextZoom, 1, 4));
  }

  function openViewer(): void {
    setZoom(1);
    setViewerOpen(true);
  }

  function closeViewer(): void {
    setViewerOpen(false);
    setZoom(1);
  }

  function renderImage(style: object) {
    if (Platform.OS === 'web') {
      return createElement('img', {
        alt: 'Uploaded screenshot',
        src: source.uri,
        style: {
          display: 'block',
          height: '100%',
          objectFit: 'contain',
          width: '100%',
        },
      });
    }

    return <RNImage source={{ uri: source.uri }} resizeMode="contain" style={style} />;
  }

  return (
    <>
      <View style={styles.screenshotCard}>
        <View style={styles.screenshotCardHeader}>
          <View style={styles.screenshotCardTitleWrap}>
            <Text style={styles.detectedLabel}>Uploaded image</Text>
            <Text style={styles.screenshotCardText}>Check the OCR result against the original screenshot.</Text>
          </View>

          <Pressable onPress={openViewer} style={({ pressed }) => [styles.viewerActionButton, pressed && styles.buttonPressed]}>
            <Text style={styles.viewerActionButtonText}>Full screen</Text>
          </Pressable>
        </View>

        <Pressable
          accessibilityHint="Open uploaded image in full screen"
          accessibilityRole="button"
          onPress={openViewer}
          style={({ pressed }) => [styles.screenshotPreviewFrame, pressed && styles.buttonPressed]}
        >
          <View style={[styles.screenshotPreviewImage, { height: previewHeight }]}>
            {renderImage(styles.screenshotPreviewNativeImage)}
          </View>
          <View style={styles.screenshotPreviewFooter}>
            <PressableCue label="Tap to zoom" />
          </View>
        </Pressable>
      </View>

      <Modal animationType="fade" onRequestClose={closeViewer} transparent visible={viewerOpen}>
        <View style={styles.imageViewerBackdrop}>
          <View style={styles.imageViewerPanel}>
            <View style={styles.imageViewerHeader}>
              <View style={styles.imageViewerTitleWrap}>
                <Text style={styles.sectionTitle}>Uploaded image</Text>
                <Text style={styles.helperText}>Zoom in to verify the detected module and codes.</Text>
              </View>

              <Pressable onPress={closeViewer} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                <Text style={styles.pageBackText}>Close</Text>
              </Pressable>
            </View>

            <View style={styles.imageViewerToolbar}>
              <Pressable
                disabled={zoom <= 1}
                onPress={() => adjustZoom(zoom - 0.25)}
                style={({ pressed }) => [styles.viewerToolButton, zoom <= 1 && styles.buttonDisabled, pressed && styles.buttonPressed]}
              >
                <Text style={styles.viewerToolButtonText}>-</Text>
              </Pressable>

              <Text style={styles.imageViewerZoomText}>{Math.round(zoom * 100)}%</Text>

              <Pressable
                disabled={zoom >= 4}
                onPress={() => adjustZoom(zoom + 0.25)}
                style={({ pressed }) => [styles.viewerToolButton, zoom >= 4 && styles.buttonDisabled, pressed && styles.buttonPressed]}
              >
                <Text style={styles.viewerToolButtonText}>+</Text>
              </Pressable>

              <Pressable onPress={() => adjustZoom(1)} style={({ pressed }) => [styles.viewerActionButton, pressed && styles.buttonPressed]}>
                <Text style={styles.viewerActionButtonText}>Reset</Text>
              </Pressable>
            </View>

            <View style={[styles.imageViewerViewport, { maxHeight: modalViewportHeight }]}>
              <ScrollView
                contentContainerStyle={[styles.imageViewerHorizontalContent, { minHeight: modalViewportHeight, minWidth: modalViewportWidth }]}
                horizontal
                maximumZoomScale={4}
                minimumZoomScale={1}
              >
                <ScrollView contentContainerStyle={[styles.imageViewerVerticalContent, { minHeight: modalViewportHeight }]}>
                  <View style={[styles.imageViewerImageFrame, { height: zoomedHeight, width: zoomedWidth }]}>
                    {renderImage(styles.imageViewerImage)}
                  </View>
                </ScrollView>
              </ScrollView>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

function WebHalftoneCanvas({ viewportHeight, viewportWidth }: { viewportHeight: number; viewportWidth: number }) {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const spec = useMemo(() => buildCanvasDotSpec(viewportWidth, viewportHeight), [viewportHeight, viewportWidth]);
  const overscan = spec.cellSize * 10;

  useEffect(() => {
    const canvas = canvasRef.current;

    if (!canvas) {
      return undefined;
    }

    const context = canvas.getContext('2d');

    if (!context) {
      return undefined;
    }

    const drawContext = context;
    const pixelRatio = Math.max(1, Math.min(globalThis.devicePixelRatio || 1, 2));
    let animationFrame = 0;
    canvas.width = Math.round(spec.width * pixelRatio);
    canvas.height = Math.round(spec.height * pixelRatio);
    drawContext.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
    let startTimestamp: number | null = null;

    function draw(timestamp: number): void {
      if (startTimestamp === null) {
        startTimestamp = timestamp;
      }

      const phase = (timestamp - startTimestamp) * cloudPhaseSpeed;

      drawHalftoneGradient(drawContext, spec.width, spec.height);

      for (let index = 0; index < spec.dots.length; index += 1) {
        const dot = spec.dots[index];
        const opacity = dotOpacityAt(dot.xNorm, dot.yNorm, dot.row, dot.column, phase);

        if (opacity < 0.075) {
          continue;
        }

        const bright = opacity > 0.78;
        const colorBlend = dot.tone === 'ghost' ? 0 : smoothstep(0.08, 0.78, dot.accentStrength) * smoothstep(0.18, 0.52, opacity);
        const size = bright || colorBlend > 0.52 ? 3.2 : opacity > 0.56 ? 2.8 : 2.35;
        const halfSize = size / 2;

        drawContext.fillStyle = colorForDotTone(dot.tone, opacity, colorBlend);

        drawContext.fillRect(dot.x - halfSize, dot.y - halfSize, size, size);
      }

      animationFrame = globalThis.requestAnimationFrame(draw);
    }

    animationFrame = globalThis.requestAnimationFrame(draw);

    return () => {
      globalThis.cancelAnimationFrame(animationFrame);
    };
  }, [spec]);

  return createElement('canvas', {
    ref: canvasRef,
    style: {
      height: spec.height,
      left: -overscan,
      pointerEvents: 'none',
      position: 'absolute',
      top: -overscan,
      width: spec.width,
    },
  });
}

function colorForDotTone(tone: DotTone, opacity: number, blend: number): string {
  const defaultColor = opacity > 0.78 ? [255, 252, 245] : opacity > 0.56 ? [244, 246, 248] : [214, 224, 238];
  let accentColor = defaultColor;

  switch (tone) {
    case 'aqua':
      accentColor = [89, 242, 211];
      break;
    case 'blue':
      accentColor = [118, 154, 255];
      break;
    case 'cyan':
      accentColor = [79, 229, 255];
      break;
    case 'violet':
      accentColor = [174, 146, 255];
      break;
    default:
      accentColor = defaultColor;
  }

  const amount = clamp(blend * 0.78, 0, 0.78);
  const red = Math.round(lerp(defaultColor[0], accentColor[0], amount));
  const green = Math.round(lerp(defaultColor[1], accentColor[1], amount));
  const blue = Math.round(lerp(defaultColor[2], accentColor[2], amount));
  const alpha = Math.min(0.96, opacity * (1 + amount * 0.05));

  return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

function drawHalftoneGradient(context: CanvasRenderingContext2D, width: number, height: number): void {
  const base = context.createLinearGradient(0, 0, 0, height);

  base.addColorStop(0, '#0f121b');
  base.addColorStop(0.34, '#070914');
  base.addColorStop(0.58, '#020307');
  base.addColorStop(1, '#080b12');
  context.fillStyle = base;
  context.fillRect(0, 0, width, height);

  const depthWash = context.createLinearGradient(0, 0, width, 0);

  depthWash.addColorStop(0, 'rgba(97, 123, 255, 0.05)');
  depthWash.addColorStop(0.45, 'rgba(0, 0, 0, 0)');
  depthWash.addColorStop(1, 'rgba(38, 209, 238, 0.045)');
  context.fillStyle = depthWash;
  context.fillRect(0, 0, width, height);

  const verticalWash = context.createLinearGradient(0, 0, 0, height);

  verticalWash.addColorStop(0, 'rgba(255, 255, 255, 0.028)');
  verticalWash.addColorStop(0.36, 'rgba(0, 0, 0, 0)');
  verticalWash.addColorStop(0.68, 'rgba(0, 0, 0, 0.06)');
  verticalWash.addColorStop(1, 'rgba(143, 183, 255, 0.035)');
  context.fillStyle = verticalWash;
  context.fillRect(0, 0, width, height);

  const centerTone = context.createLinearGradient(width * 0.18, 0, width * 0.82, 0);

  centerTone.addColorStop(0, 'rgba(0, 0, 0, 0.08)');
  centerTone.addColorStop(0.5, 'rgba(10, 16, 26, 0.045)');
  centerTone.addColorStop(1, 'rgba(0, 0, 0, 0.08)');
  context.fillStyle = centerTone;
  context.fillRect(0, 0, width, height);
}

function DotMesh({ cellSize, phase, rows }: { cellSize: number; phase: Animated.Value; rows: DotCell[][] }) {
  return (
    <>
      {rows.map((row, rowIndex) => (
        <View key={rowIndex} style={[styles.dotRow, { height: cellSize }]}>
          {row.map((dot) => (
            <Animated.View
              key={dot.key}
              style={[
                styles.dotCell,
                { height: cellSize, opacity: phase.interpolate({ inputRange: cloudInputRange, outputRange: dot.opacityFrames }), width: cellSize },
              ]}
            >
              <View
                style={[
                  styles.dot,
                dot.tone === 'dim' && styles.dotDim,
                dot.tone === 'mid' && styles.dotMid,
                dot.tone === 'bright' && styles.dotBright,
                dot.tone === 'aqua' && styles.dotAqua,
                dot.tone === 'blue' && styles.dotBlue,
                dot.tone === 'cyan' && styles.dotCyan,
                dot.tone === 'violet' && styles.dotViolet,
              ]}
            />
            </Animated.View>
          ))}
        </View>
      ))}
    </>
  );
}

function formatPercent(value: number | null | undefined): string {
  return typeof value === 'number' ? `${Math.round(value * 100)}%` : 'n/a';
}

function formatHistoryTimestamp(value: string | null): string {
  if (!value) {
    return 'Unknown time';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  const day = `${date.getDate()}`.padStart(2, '0');
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const year = date.getFullYear();
  const hours = `${date.getHours()}`.padStart(2, '0');
  const minutes = `${date.getMinutes()}`.padStart(2, '0');

  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function codeFromCandidate(candidate: DiagnosisCandidate): string {
  return candidate.candidate_code ?? candidate.normalized_code ?? '';
}

function historyCodeChips(item: DiagnosisHistoryItem | null, detail: DiagnosisDetail | null = null): HistoryCodeChip[] {
  const candidates = detail?.candidates?.length ? detail.candidates : item?.candidates ?? [];
  const candidateChips = candidates
    .map((candidate) => ({
      code: codeFromCandidate(candidate),
      colorMeaning: candidateColorMeaning(candidate),
    }))
    .filter((chip) => chip.code !== '');

  if (candidateChips.length > 0) {
    return candidateChips;
  }

  if (!item) {
    return [];
  }

  const codes = item.user_entered_codes.length > 0 ? item.user_entered_codes : item.ai_detected_codes;

  if (codes.length > 0) {
    return codes.map((code) => ({ code, colorMeaning: null }));
  }

  const fallbackCode = item.selected_diagnostic_entry?.primary_code;

  return fallbackCode ? [{ code: fallbackCode, colorMeaning: null }] : [];
}

function normalizeHexColor(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  const trimmed = value.trim();

  if (/^#[0-9a-f]{6}$/i.test(trimmed)) {
    return trimmed;
  }

  if (/^#[0-9a-f]{3}$/i.test(trimmed)) {
    const [, red, green, blue] = trimmed;

    return `#${red}${red}${green}${green}${blue}${blue}`;
  }

  return null;
}

function hexToRgb(hex: string): { blue: number; green: number; red: number } | null {
  const normalized = normalizeHexColor(hex);

  if (!normalized) {
    return null;
  }

  return {
    red: Number.parseInt(normalized.slice(1, 3), 16),
    green: Number.parseInt(normalized.slice(3, 5), 16),
    blue: Number.parseInt(normalized.slice(5, 7), 16),
  };
}

function rgbaFromHex(hex: string, alpha: number): string {
  const rgb = hexToRgb(hex);

  if (!rgb) {
    return `rgba(143, 183, 255, ${alpha})`;
  }

  return `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, ${alpha})`;
}

function textColorForHex(hex: string): string {
  const rgb = hexToRgb(hex);

  if (!rgb) {
    return '#f7f8fb';
  }

  const luminance = (0.2126 * rgb.red + 0.7152 * rgb.green + 0.0722 * rgb.blue) / 255;

  return luminance > 0.58 ? '#111827' : '#ffffff';
}

function historyMatchLabel(item: DiagnosisHistoryItem): string | null {
  const entry = item.selected_diagnostic_entry;

  if (!entry) {
    return null;
  }

  return [entry.module_key, entry.primary_code, entry.title].filter(Boolean).join(' · ') || null;
}

type DocumentationNode = {
  attrs?: Record<string, unknown>;
  content?: DocumentationNode[];
  marks?: Array<{ attrs?: Record<string, unknown>; type?: string }>;
  text?: string;
  type?: string;
};

function getEntryDocumentations(entry: DiagnosticEntry | null): CodeDocumentation[] {
  return entry?.code_documentations ?? [];
}

function extractDocumentationNodes(content: unknown): DocumentationNode[] {
  if (!content || typeof content !== 'object') {
    return [];
  }

  const node = content as DocumentationNode;

  if (node.type === 'doc' && Array.isArray(node.content)) {
    return node.content;
  }

  return Array.isArray(node.content) ? node.content : [node];
}

function normalizeDocumentationUrl(value: unknown): string | null {
  return typeof value === 'string' && value.trim() !== '' ? resolveAssetUrl(value) : null;
}

function extractYouTubeVideoId(url: string): string | null {
  const patterns = [
    /youtu\.be\/([A-Za-z0-9_-]{6,})/i,
    /[?&]v=([A-Za-z0-9_-]{6,})/i,
    /youtube\.com\/embed\/([A-Za-z0-9_-]{6,})/i,
    /youtube\.com\/shorts\/([A-Za-z0-9_-]{6,})/i,
  ];

  for (const pattern of patterns) {
    const match = url.match(pattern);

    if (match?.[1]) {
      return match[1];
    }
  }

  return null;
}

function renderYouTubeEmbed(node: DocumentationNode, key: string): ReactNode {
  const config = node.attrs?.config;
  const blockConfig = config && typeof config === 'object' ? config as Record<string, unknown> : {};
  const rawUrl = typeof blockConfig.url === 'string' ? blockConfig.url : '';
  const videoId = extractYouTubeVideoId(rawUrl);
  const title = typeof blockConfig.title === 'string' && blockConfig.title.trim() !== '' ? blockConfig.title.trim() : 'YouTube video';

  if (!videoId || rawUrl.trim() === '') {
    return null;
  }

  const embedUrl = `https://www.youtube-nocookie.com/embed/${videoId}`;

  if (Platform.OS === 'web') {
    return (
      <View key={key} style={styles.documentationVideoBlock}>
        {createElement('iframe', {
          allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
          allowFullScreen: true,
          src: embedUrl,
          style: styles.documentationVideoFrame,
          title,
        })}
        <Text style={styles.documentationVideoTitle}>{title}</Text>
      </View>
    );
  }

  return (
    <Pressable
      key={key}
      onPress={() => {
        void Linking.openURL(rawUrl);
      }}
      style={({ pressed }) => [styles.documentationVideoLinkCard, pressed && styles.buttonPressed]}
    >
      <View style={styles.documentationVideoThumb}>
        <Text style={styles.documentationVideoPlay}>▶</Text>
      </View>
      <View style={styles.documentationVideoCopy}>
        <Text style={styles.documentationVideoTitle}>{title}</Text>
        <Text style={styles.documentationVideoMeta}>Open YouTube video</Text>
      </View>
    </Pressable>
  );
}

function plainTextFromDocumentationNodes(nodes: DocumentationNode[] | undefined): string {
  if (!Array.isArray(nodes) || nodes.length === 0) {
    return '';
  }

  return nodes
    .map((node) => {
      if (typeof node.text === 'string') {
        return node.text;
      }

      return plainTextFromDocumentationNodes(node.content);
    })
    .join('');
}

function documentationTextStyle(node: DocumentationNode, baseStyle: object): object[] {
  const stylesForNode: object[] = [baseStyle];

  for (const mark of node.marks ?? []) {
    switch (mark.type) {
      case 'bold':
        stylesForNode.push(styles.documentationBoldText);
        break;
      case 'italic':
        stylesForNode.push(styles.documentationItalicText);
        break;
      case 'underline':
        stylesForNode.push(styles.documentationUnderlineText);
        break;
      case 'strike':
        stylesForNode.push(styles.documentationStrikeText);
        break;
      case 'code':
        stylesForNode.push(styles.documentationInlineCodeText);
        break;
      case 'link':
        stylesForNode.push(styles.documentationLinkText);
        break;
      default:
        break;
    }
  }

  return stylesForNode;
}

function renderDocumentationInline(node: DocumentationNode, key: string, baseStyle: object): ReactNode {
  if (node.type === 'hardBreak') {
    return <Text key={key}>{'\n'}</Text>;
  }

  if (node.type === 'image') {
    const source = normalizeDocumentationUrl(node.attrs?.src ?? node.attrs?.url);

    if (!source) {
      return null;
    }

    return <RNImage key={key} source={{ uri: source }} style={styles.documentationInlineImage} resizeMode="contain" />;
  }

  if (typeof node.text === 'string') {
    return (
      <Text key={key} style={documentationTextStyle(node, baseStyle)}>
        {node.text}
      </Text>
    );
  }

  const children = Array.isArray(node.content)
    ? node.content.map((child, index) => renderDocumentationInline(child, `${key}-${index}`, baseStyle))
    : null;

  return (
    <Text key={key} style={documentationTextStyle(node, baseStyle)}>
      {children}
    </Text>
  );
}

function renderDocumentationBlock(node: DocumentationNode, key: string): ReactNode {
  const children = Array.isArray(node.content) ? node.content : [];
  const level = typeof node.attrs?.level === 'number' ? node.attrs.level : 2;

  switch (node.type) {
    case 'heading':
      return (
        <Text key={key} style={[styles.documentationHeading, level <= 2 ? styles.documentationHeadingLarge : styles.documentationHeadingSmall]}>
          {children.map((child, index) => renderDocumentationInline(child, `${key}-${index}`, styles.documentationHeadingText))}
        </Text>
      );
    case 'paragraph':
      if (children.length === 0) {
        return null;
      }

      if (children.some((child) => child.type === 'image')) {
        return (
          <View key={key} style={styles.documentationParagraphMedia}>
            {children.map((child, index) =>
              child.type === 'image' ? (
                renderDocumentationBlock(child, `${key}-${index}`)
              ) : (
                <Text key={`${key}-${index}`} style={styles.documentationParagraph}>
                  {renderDocumentationInline(child, `${key}-${index}-inline`, styles.documentationParagraphText)}
                </Text>
              ),
            )}
          </View>
        );
      }

      return (
        <Text key={key} style={styles.documentationParagraph}>
          {children.map((child, index) => renderDocumentationInline(child, `${key}-${index}`, styles.documentationParagraphText))}
        </Text>
      );
    case 'bulletList':
      return (
        <View key={key} style={styles.documentationList}>
          {children.map((child, index) => (
            <View key={`${key}-${index}`} style={styles.documentationListItemRow}>
              <Text style={styles.documentationBullet}>•</Text>
              <View style={styles.documentationListItemBody}>
                {Array.isArray(child.content)
                  ? child.content.map((grandChild, grandChildIndex) => renderDocumentationBlock(grandChild, `${key}-${index}-${grandChildIndex}`))
                  : null}
              </View>
            </View>
          ))}
        </View>
      );
    case 'orderedList':
      return (
        <View key={key} style={styles.documentationList}>
          {children.map((child, index) => (
            <View key={`${key}-${index}`} style={styles.documentationListItemRow}>
              <Text style={styles.documentationBullet}>{index + 1}.</Text>
              <View style={styles.documentationListItemBody}>
                {Array.isArray(child.content)
                  ? child.content.map((grandChild, grandChildIndex) => renderDocumentationBlock(grandChild, `${key}-${index}-${grandChildIndex}`))
                  : null}
              </View>
            </View>
          ))}
        </View>
      );
    case 'blockquote':
      return (
        <View key={key} style={styles.documentationQuote}>
          {children.map((child, index) => renderDocumentationBlock(child, `${key}-${index}`))}
        </View>
      );
    case 'codeBlock': {
      const codeText = plainTextFromDocumentationNodes(children);

      if (codeText === '') {
        return null;
      }

      return (
        <View key={key} style={styles.documentationCodeBlock}>
          <Text style={styles.documentationCodeBlockText}>{codeText}</Text>
        </View>
      );
    }
    case 'image': {
      const source = normalizeDocumentationUrl(node.attrs?.src ?? node.attrs?.url);

      if (!source) {
        return null;
      }

      return <RNImage key={key} source={{ uri: source }} style={styles.documentationImage} resizeMode="contain" />;
    }
    case 'table':
      return (
        <View key={key} style={styles.documentationTable}>
          {children.map((row, rowIndex) => (
            <View key={`${key}-${rowIndex}`} style={styles.documentationTableRow}>
              {(row.content ?? []).map((cell, cellIndex) => (
                <View
                  key={`${key}-${rowIndex}-${cellIndex}`}
                  style={[
                    styles.documentationTableCell,
                    cell.type === 'tableHeader' && styles.documentationTableHeaderCell,
                  ]}
                >
                  {(cell.content ?? []).map((cellChild, childIndex) => renderDocumentationBlock(cellChild, `${key}-${rowIndex}-${cellIndex}-${childIndex}`))}
                </View>
              ))}
            </View>
          ))}
        </View>
      );
    case 'horizontalRule':
      return <View key={key} style={styles.documentationDivider} />;
    case 'customBlock':
      if (node.attrs?.id === 'youtubeEmbed') {
        return renderYouTubeEmbed(node, key);
      }

      return null;
    default:
      if (children.length === 0 && typeof node.text === 'string') {
        return (
          <Text key={key} style={styles.documentationParagraph}>
            {node.text}
          </Text>
        );
      }

      return (
        <View key={key} style={styles.documentationFallbackBlock}>
          {children.map((child, index) => renderDocumentationBlock(child, `${key}-${index}`))}
        </View>
      );
  }
}

function DocumentationContent({ documentation }: { documentation: CodeDocumentation }) {
  const nodes = extractDocumentationNodes(documentation.content);

  if (nodes.length === 0) {
    return <Text style={styles.modalMessageText}>No documentation body is available.</Text>;
  }

  return <View style={styles.documentationContent}>{nodes.map((node, index) => renderDocumentationBlock(node, `${documentation.id}-${index}`))}</View>;
}

function resultString(detail: DiagnosisDetail | null, key: string): string {
  const value = detail?.result?.[key];

  return typeof value === 'string' ? value : '';
}

function codesFromCandidates(candidates: DiagnosisCandidate[]): string {
  return candidates
    .map((candidate) => candidate.candidate_code ?? candidate.normalized_code)
    .filter(Boolean)
    .join(' ');
}

function createConfirmRow(code = '', colorMeaningId: number | null = null): ConfirmCodeRow {
  return {
    code,
    colorMeaningId,
    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
  };
}

function rowsFromCandidates(candidates: DiagnosisCandidate[]): ConfirmCodeRow[] {
  const rows = candidates
    .map((candidate) => {
      const code = candidate.candidate_code ?? candidate.normalized_code ?? '';

      if (!code) {
        return null;
      }

      return createConfirmRow(code, candidate.dashboard_color_meaning?.id ?? null);
    })
    .filter((row): row is ConfirmCodeRow => row !== null);

  return rows.length > 0 ? rows : [createConfirmRow()];
}

function confirmEntriesFromRows(rows: ConfirmCodeRow[]): Array<{ code: string; dashboard_color_meaning_id: number | null }> {
  return rows
    .map((row) => ({
      code: row.code.trim(),
      dashboard_color_meaning_id: row.colorMeaningId,
    }))
    .filter((entry) => entry.code !== '');
}

function colorMeaningsForMachine(machine: Machine | null): DashboardColorMeaning[] {
  return machine?.dashboard_color_meanings ?? [];
}

function candidateColorMeaning(candidate: DiagnosisCandidate | null): DashboardColorMeaning | null {
  if (!candidate) {
    return null;
  }

  if (candidate.dashboard_color_meaning) {
    return candidate.dashboard_color_meaning;
  }

  const metadata = candidate.metadata ?? null;
  const label = typeof metadata?.color_status_label === 'string' ? metadata.color_status_label : null;

  if (!label) {
    return null;
  }

  return {
    id: 0,
    label,
    ai_key: typeof metadata?.color_status_key === 'string' ? metadata.color_status_key : null,
    hex_color: '#8fb7ff',
    description: typeof metadata?.color_status_description === 'string' ? metadata.color_status_description : null,
    priority: null,
  };
}

function candidatePriority(candidate: DiagnosisCandidate): number {
  return candidate.dashboard_color_meaning?.priority ?? -1;
}

function sortCandidatesForResult(candidates: DiagnosisCandidate[]): DiagnosisCandidate[] {
  return [...candidates].sort((first, second) => {
    const priorityDifference = candidatePriority(second) - candidatePriority(first);

    if (priorityDifference !== 0) {
      return priorityDifference;
    }

    return (first.candidate_code ?? first.normalized_code ?? '').localeCompare(second.candidate_code ?? second.normalized_code ?? '');
  });
}

function moduleFromDiagnosis(detail: DiagnosisDetail | null): string {
  const fromResult = resultString(detail, 'module_key');

  if (fromResult) {
    return fromResult;
  }

  const metadata = detail?.candidates?.[0]?.metadata ?? null;
  const fromMetadata = metadata?.module_key;

  return typeof fromMetadata === 'string' ? fromMetadata : 'PLUGSA';
}

function Stepper({ currentStep }: { currentStep: DiagnosisStep }) {
  const currentIndex = diagnosisSteps.findIndex((step) => step.id === currentStep);
  const pulse = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, {
          toValue: 1,
          duration: 1200,
          easing: Easing.out(Easing.cubic),
          useNativeDriver: true,
        }),
        Animated.timing(pulse, {
          toValue: 0,
          duration: 900,
          easing: Easing.in(Easing.cubic),
          useNativeDriver: true,
        }),
      ]),
    );

    loop.start();

    return () => loop.stop();
  }, [pulse]);

  const pulseStyle = {
    opacity: pulse.interpolate({ inputRange: [0, 1], outputRange: [0.18, 0.52] }),
    transform: [{ scale: pulse.interpolate({ inputRange: [0, 1], outputRange: [1, 1.42] }) }],
  };

  return (
    <View style={styles.stepper}>
      {diagnosisSteps.map((step, index) => {
        const active = step.id === currentStep;
        const done = index < currentIndex;

        return (
          <View key={step.id} style={styles.stepItem}>
            <View style={[styles.stepDot, active && styles.stepDotActive, done && styles.stepDotDone]}>
              {active ? <Animated.View style={[styles.stepPulse, pulseStyle]} /> : null}
              <Text style={[styles.stepNumber, active && styles.stepNumberActive, done && !active && styles.stepNumberDone]}>
                {index + 1}
              </Text>
            </View>
            <Text style={[styles.stepText, active && styles.stepTextActive]}>{step.title}</Text>
          </View>
        );
      })}
    </View>
  );
}

function QualityBadge({ quality }: { quality: ImageQuality | null }) {
  if (!quality) {
    return null;
  }

  const label = quality.status === 'pass' ? 'Quality good' : quality.status === 'warn' ? 'Check image' : 'Retake image';

  return (
    <View style={[styles.qualityBox, quality.status === 'pass' && styles.qualityPass, quality.status === 'warn' && styles.qualityWarn, quality.status === 'fail' && styles.qualityFail]}>
      <Text style={styles.qualityTitle}>{label}</Text>
      {quality.messages.map((message) => (
        <Text key={message} style={styles.qualityText}>{message}</Text>
      ))}
      {quality.sharpness !== null ? <Text style={styles.qualityMeta}>Sharpness score: {quality.sharpness}</Text> : null}
    </View>
  );
}

function ResultPanel({ detail }: { detail: DiagnosisDetail | null }) {
  const entry = detail?.selected_diagnostic_entry ?? null;
  const candidates = detail?.candidates ?? [];
  const sortedCandidates = sortCandidatesForResult(candidates);
  const selectedCandidate = entry
    ? sortedCandidates.find((candidate) => candidate.matched_diagnostic_entry?.id === entry.id) ?? sortedCandidates[0] ?? null
    : null;
  const message = typeof detail?.result?.message === 'string' ? detail.result.message : null;
  const [documentationModal, setDocumentationModal] = useState<{ code: string; documentations: CodeDocumentation[] } | null>(null);

  useEffect(() => {
    setDocumentationModal(null);
  }, [detail?.id]);

  if (!detail) {
    return (
      <View style={styles.resultPanel}>
        <View style={styles.resultBody}>
          <Text style={styles.resultTitle}>No diagnosis yet</Text>
          <Text style={styles.helperText}>Complete the previous steps to see the error preview.</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.resultPanel}>
      <View style={styles.resultHeader}>
        <View>
          <Text style={styles.kicker}>Scan result</Text>
          <Text style={styles.resultTitle}>{detail.machine?.name ?? 'Diagnosis'}</Text>
        </View>
        <Text style={styles.badge}>{detail.id.slice(-6)}</Text>
      </View>

      <View style={styles.resultBody}>
        {message ? <Text style={styles.helperText}>{message}</Text> : null}

        {entry ? (
          <ErrorPreviewCard candidate={selectedCandidate} entry={entry} onOpenDocumentation={setDocumentationModal} />
        ) : sortedCandidates.length ? (
          <View style={styles.matchList}>
            {sortedCandidates.map((candidate) => (
              <ErrorPreviewCard
                key={candidate.id}
                candidate={candidate}
                entry={candidate.matched_diagnostic_entry ?? null}
                onOpenDocumentation={setDocumentationModal}
              />
            ))}
          </View>
        ) : (
          <Text style={styles.helperText}>No visible code was confirmed yet.</Text>
        )}
      </View>

      <DocumentationModal modal={documentationModal} onClose={() => setDocumentationModal(null)} />
    </View>
  );
}

function HistoryOverviewPanel({
  detail,
  item,
}: {
  detail: DiagnosisDetail | null;
  item: DiagnosisHistoryItem | null;
}) {
  const matchedLabel = item ? historyMatchLabel(item) : null;
  const codeChips = historyCodeChips(item, detail);
  const machineLabel = item?.machine ? [item.machine.name, item.machine.manufacturer, item.machine.model_number].filter(Boolean).join(' · ') : null;
  const screenshotSource = detail?.screenshot_url
    ? {
        uri: resolveAssetUrl(detail.screenshot_url),
      }
    : item?.screenshot_url
      ? {
          uri: resolveAssetUrl(item.screenshot_url),
        }
      : null;

  if (!detail && !item) {
    return (
      <View style={styles.resultPanel}>
        <View style={styles.resultBody}>
          <Text style={styles.resultTitle}>Scan not found</Text>
          <Text style={styles.helperText}>Choose a scan from the history list.</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.scanOverviewStack}>
      <View style={styles.scanOverviewMetaCard}>
        <View style={styles.scanOverviewMetaRow}>
          <View style={styles.scanOverviewMetaBlock}>
            <Text style={styles.detectedLabel}>Machine</Text>
            <Text style={styles.scanOverviewMetaValue}>{machineLabel ?? 'Unknown machine'}</Text>
          </View>
          <View style={styles.scanOverviewMetaBlock}>
            <Text style={styles.detectedLabel}>Scanned</Text>
            <Text style={styles.scanOverviewMetaValue}>{formatHistoryTimestamp(item?.created_at ?? null)}</Text>
          </View>
        </View>

        <View style={styles.scanOverviewMetaRow}>
          <View style={styles.scanOverviewMetaBlock}>
            <Text style={styles.detectedLabel}>Captured codes</Text>
            <HistoryCodeChips chips={codeChips} size="large" />
          </View>
        </View>

        {matchedLabel ? (
          <View style={styles.scanOverviewMatchedBox}>
            <Text style={styles.detectedLabel}>Matched entry</Text>
            <Text style={styles.scanOverviewMatchedText}>{matchedLabel}</Text>
          </View>
        ) : null}
      </View>

      {screenshotSource ? <ScreenshotViewer source={screenshotSource} /> : null}
      <ResultPanel detail={detail} />
    </View>
  );
}

function PressableCue({ label }: { label: string }) {
  return (
    <View style={styles.pressableCue}>
      <Text style={styles.pressableCueText}>{label}</Text>
      <Text style={styles.pressableCueArrow}>›</Text>
    </View>
  );
}

function HistoryCodeChips({
  chips,
  emptyLabel = 'No code captured',
  size = 'default',
}: {
  chips: HistoryCodeChip[];
  emptyLabel?: string;
  size?: 'default' | 'large';
}) {
  if (chips.length === 0) {
    return <Text style={size === 'large' ? styles.scanOverviewCodeValue : styles.scanHistoryCodes}>{emptyLabel}</Text>;
  }

  return (
    <View style={[styles.historyCodeChipRow, size === 'large' && styles.historyCodeChipRowLarge]}>
      {chips.map((chip, index) => {
        const hexColor = normalizeHexColor(chip.colorMeaning?.hex_color);
        const chipStyle = hexColor
          ? {
              backgroundColor: rgbaFromHex(hexColor, 0.18),
              borderColor: rgbaFromHex(hexColor, 0.62),
              shadowColor: hexColor,
            }
          : null;
        const textStyle = hexColor ? { color: '#f7f8fb' } : null;

        return (
          <View
            key={`${chip.code}-${chip.colorMeaning?.id ?? 'none'}-${index}`}
            style={[
              styles.historyCodeChip,
              size === 'large' && styles.historyCodeChipLarge,
              chipStyle,
            ]}
          >
            <Text numberOfLines={1} style={[styles.historyCodeChipText, size === 'large' && styles.historyCodeChipTextLarge, textStyle]}>
              {chip.code}
            </Text>
          </View>
        );
      })}
    </View>
  );
}

function DashboardActionTile({
  disabled = false,
  fullWidth = false,
  hint = 'Open',
  label,
  meta,
  onPress,
  tone = 'neutral',
  value,
}: {
  disabled?: boolean;
  fullWidth?: boolean;
  hint?: string;
  label: string;
  meta: string;
  onPress: () => void;
  tone?: 'neutral' | 'primary' | 'warning';
  value: string;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityHint={hint}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [
        styles.dashboardActionTile,
        fullWidth && styles.dashboardActionTileFullWidth,
        tone === 'primary' && styles.dashboardActionTilePrimary,
        tone === 'warning' && styles.dashboardActionTileWarning,
        disabled && styles.dashboardActionTileDisabled,
        pressed && styles.buttonPressed,
      ]}
    >
      <Text style={[styles.dashboardActionLabel, tone === 'primary' && styles.dashboardActionLabelPrimary]}>{label}</Text>
      <Text
        numberOfLines={2}
        style={[
          styles.dashboardActionValue,
          tone === 'primary' && styles.dashboardActionValuePrimary,
          disabled && styles.dashboardActionValueDisabled,
        ]}
      >
        {value}
      </Text>
      <Text
        numberOfLines={2}
        style={[
          styles.dashboardActionMeta,
          tone === 'primary' && styles.dashboardActionMetaPrimary,
          disabled && styles.dashboardActionMetaDisabled,
        ]}
      >
        {meta}
      </Text>
      <View style={styles.dashboardActionFooter}>
        <PressableCue label={hint} />
      </View>
    </Pressable>
  );
}

function DashboardHistoryRow({
  item,
  onPress,
}: {
  item: DiagnosisHistoryItem;
  onPress: (item: DiagnosisHistoryItem) => void;
}) {
  const codeChips = historyCodeChips(item);

  return (
    <Pressable
      accessibilityHint="Open scan overview"
      accessibilityRole="button"
      onPress={() => onPress(item)}
      style={({ pressed }) => [styles.dashboardHistoryRow, pressed && styles.buttonPressed]}
    >
      <View style={styles.dashboardHistoryRowTop}>
        <Text numberOfLines={1} style={styles.dashboardHistoryRowMachine}>
          {item.machine?.name ?? 'Unknown machine'}
        </Text>
      </View>
      <HistoryCodeChips chips={codeChips} />
      <View style={styles.dashboardHistoryRowFooter}>
        <Text numberOfLines={1} style={styles.dashboardHistoryRowMeta}>
          {formatHistoryTimestamp(item.created_at)}
        </Text>
        <PressableCue label="Open scan" />
      </View>
    </Pressable>
  );
}

function ErrorPreviewCard({
  candidate,
  entry,
  onOpenDocumentation,
}: {
  candidate: DiagnosisCandidate | null;
  entry: DiagnosticEntry | null;
  onOpenDocumentation: (value: { code: string; documentations: CodeDocumentation[] } | null) => void;
}) {
  const code = entry?.primary_code ?? candidate?.candidate_code ?? candidate?.normalized_code ?? 'Code';
  const module = entry?.module_key ?? (typeof candidate?.metadata?.module_key === 'string' ? candidate.metadata.module_key : 'Not matched');
  const documentations = getEntryDocumentations(entry);
  const colorMeaning = candidateColorMeaning(candidate);
  const colorAccent = colorMeaning?.hex_color ?? '#8fb7ff';
  const normalizedColorAccent = normalizeHexColor(colorAccent) ?? '#8fb7ff';

  function renderColorWash() {
    if (Platform.OS === 'web') {
      return createElement('div', {
        style: {
          background: `linear-gradient(90deg, ${rgbaFromHex(normalizedColorAccent, 0.16)} 0%, ${rgbaFromHex(normalizedColorAccent, 0.06)} 34%, rgba(255, 255, 255, 0) 100%)`,
          bottom: 0,
          left: 0,
          pointerEvents: 'none',
          position: 'absolute',
          right: 0,
          top: 0,
        },
      });
    }

    return <View pointerEvents="none" style={[styles.matchCardColorWash, { backgroundColor: rgbaFromHex(normalizedColorAccent, 0.08) }]} />;
  }

  return (
    <View style={[styles.matchCard, { borderLeftColor: normalizedColorAccent }]}>
      {renderColorWash()}
      <View style={styles.matchCardHeader}>
        <View>
          <Text style={styles.matchCode}>{code}</Text>
          <Text style={styles.matchModule}>{module}</Text>
        </View>
        <View style={styles.matchCardActions}>
          {documentations.length > 0 ? (
            <Pressable
              onPress={() => onOpenDocumentation({ code, documentations })}
              style={({ pressed }) => [styles.documentationButton, pressed && styles.buttonPressed]}
            >
              <View style={styles.documentationButtonIcon}>
                <Text style={styles.documentationButtonIconText}>i</Text>
              </View>
              <Text style={styles.documentationButtonText}>{documentations.length === 1 ? 'Doc' : `Docs ${documentations.length}`}</Text>
            </Pressable>
          ) : null}
          <Text style={styles.matchScore}>{formatPercent(candidate?.confidence ?? entry?.confidence)}</Text>
        </View>
      </View>

      {colorMeaning ? (
        <View style={styles.colorMeaningPanel}>
          <View style={[styles.colorMeaningSwatch, { backgroundColor: colorAccent }]} />
          <View style={styles.colorMeaningCopy}>
            <Text style={styles.colorMeaningLabel}>{colorMeaning.label}</Text>
            {colorMeaning.description ? <Text style={styles.colorMeaningDescription}>{colorMeaning.description}</Text> : null}
          </View>
        </View>
      ) : null}

      <Text style={styles.matchTitle}>{entry?.title || entry?.meaning || 'No manual meaning yet'}</Text>

      {entry?.meaning ? (
        <View style={styles.textBlock}>
          <Text style={styles.textLabel}>Meaning</Text>
          <Text style={styles.bodyText}>{entry.meaning}</Text>
        </View>
      ) : null}

      {entry?.recommended_action ? (
        <View style={styles.textBlock}>
          <Text style={styles.textLabel}>Action</Text>
          <Text style={styles.bodyText}>{entry.recommended_action}</Text>
        </View>
      ) : null}
    </View>
  );
}

function DocumentationModal({
  modal,
  onClose,
}: {
  modal: { code: string; documentations: CodeDocumentation[] } | null;
  onClose: () => void;
}) {
  return (
    <Modal animationType="fade" onRequestClose={onClose} transparent visible={modal !== null}>
      <View style={styles.modalBackdrop}>
        <View style={[styles.modalPanel, styles.documentationModalPanel]}>
          <View style={styles.pageTitleRow}>
            <View style={styles.documentationModalTitleWrap}>
              <Text style={[styles.sectionTitle, styles.pageTitle]}>Documentation</Text>
              <Text style={styles.helperText}>
                {modal ? `${modal.code} has ${modal.documentations.length} linked ${modal.documentations.length === 1 ? 'document' : 'documents'}.` : ''}
              </Text>
            </View>
            <Pressable onPress={onClose} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
              <Text style={styles.pageBackText}>Close</Text>
            </Pressable>
          </View>

          <ScrollView contentContainerStyle={styles.documentationModalContent}>
            {modal?.documentations.map((documentation) => (
              <View key={documentation.id} style={styles.documentationCard}>
                <Text style={styles.documentationCardTitle}>{documentation.title}</Text>
                <DocumentationContent documentation={documentation} />
              </View>
            )) ?? null}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

function LoginScreen({
  isPending,
  onLogin,
  onRegister,
  error,
}: {
  isPending: boolean;
  onLogin: (input: { email: string; password: string }) => void;
  onRegister: (input: { name: string; email: string; password: string; passwordConfirmation: string }) => void;
  error: string | null;
}) {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const isRegistering = mode === 'register';
  const canSubmit =
    email.trim() !== '' &&
    password !== '' &&
    !isPending &&
    (!isRegistering || (name.trim() !== '' && passwordConfirmation !== ''));

  function submit(): void {
    if (isRegistering) {
      onRegister({
        name: name.trim(),
        email: email.trim(),
        password,
        passwordConfirmation,
      });

      return;
    }

    onLogin({ email: email.trim(), password });
  }

  function submitFromInput(): void {
    if (canSubmit) {
      submit();
    }
  }

  function handleInputKeyPress(event: NativeSyntheticEvent<TextInputKeyPressEventData>): void {
    if (Platform.OS === 'web' && event.nativeEvent.key === 'Enter') {
      submitFromInput();
    }
  }

  return (
    <SafeAreaView style={styles.screen}>
      <AmbientBackground />
      <StatusBar style="light" />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.keyboard}>
        <ScrollView contentContainerStyle={styles.loginScrollContent} keyboardShouldPersistTaps="handled">
          <View style={styles.loginShell}>
            <View style={styles.loginWindow}>
              <View style={styles.appToolbar}>
                <View style={styles.appToolbarCopy}>
                  <Text style={styles.appToolbarLabel}>Machine Error Helper</Text>
                  <Text style={styles.appToolbarTitle}>{isRegistering ? 'Create account' : 'Sign in'}</Text>
                </View>
              </View>

              <View style={styles.loginHero}>
                <Text style={styles.eyebrow}>Machine Error Helper</Text>
                <Text style={styles.title}>{isRegistering ? 'Create your account.' : 'Sign in to continue.'}</Text>
                <Text style={styles.subtitle}>Diagnose machine dashboard alarms from screenshot to guided resolution.</Text>
              </View>

              <View style={styles.loginPanel}>
                <Text style={styles.sectionTitle}>{isRegistering ? 'Register' : 'Login'}</Text>

                <View style={styles.inputGrid}>
                  {isRegistering ? (
                    <View style={styles.inputGroup}>
                      <Text style={styles.inputLabel}>Name</Text>
                      <TextInput
                        autoCapitalize="words"
                        autoComplete="name"
                        onKeyPress={handleInputKeyPress}
                        onChangeText={setName}
                        onSubmitEditing={Platform.OS === 'web' ? undefined : submitFromInput}
                        placeholder="Operator name"
                        placeholderTextColor="#7f8490"
                        returnKeyType="done"
                        style={styles.input}
                        value={name}
                      />
                    </View>
                  ) : null}

                  <View style={styles.inputGroup}>
                    <Text style={styles.inputLabel}>Email</Text>
                    <TextInput
                      autoCapitalize="none"
                      autoComplete="email"
                      keyboardType="email-address"
                      onKeyPress={handleInputKeyPress}
                      onChangeText={setEmail}
                      onSubmitEditing={Platform.OS === 'web' ? undefined : submitFromInput}
                      placeholder="operator@example.com"
                      placeholderTextColor="#7f8490"
                      returnKeyType="done"
                      style={styles.input}
                      value={email}
                    />
                  </View>

                  <View style={styles.inputGroup}>
                    <Text style={styles.inputLabel}>Password</Text>
                    <TextInput
                      autoCapitalize="none"
                      autoComplete="password"
                      onKeyPress={handleInputKeyPress}
                      onChangeText={setPassword}
                      onSubmitEditing={Platform.OS === 'web' ? undefined : submitFromInput}
                      placeholder="Password"
                      placeholderTextColor="#7f8490"
                      returnKeyType="done"
                      secureTextEntry
                      style={styles.input}
                      value={password}
                    />
                  </View>

                  {isRegistering ? (
                    <View style={styles.inputGroup}>
                      <Text style={styles.inputLabel}>Confirm password</Text>
                      <TextInput
                        autoCapitalize="none"
                        autoComplete="password"
                        onKeyPress={handleInputKeyPress}
                        onChangeText={setPasswordConfirmation}
                        onSubmitEditing={Platform.OS === 'web' ? undefined : submitFromInput}
                        placeholder="Confirm password"
                        placeholderTextColor="#7f8490"
                        returnKeyType="done"
                        secureTextEntry
                        style={styles.input}
                        value={passwordConfirmation}
                      />
                    </View>
                  ) : null}
                </View>

                {error ? <Text style={styles.error}>{error}</Text> : null}

                <Pressable
                  disabled={!canSubmit}
                  onPress={submit}
                  style={({ pressed }) => [styles.fullButton, !canSubmit && styles.buttonDisabled, pressed && styles.buttonPressed]}
                >
                  <Text style={styles.buttonText}>{isPending ? 'Please wait' : isRegistering ? 'Create Account' : 'Sign In'}</Text>
                </Pressable>

                <Pressable
                  disabled={isPending}
                  onPress={() => setMode(isRegistering ? 'login' : 'register')}
                  style={({ pressed }) => [styles.authSwitchButton, pressed && styles.buttonPressed]}
                >
                  <Text style={styles.authSwitchText}>{isRegistering ? 'Already have an account? Sign in' : 'Need an account? Register'}</Text>
                </Pressable>
              </View>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function MachineErrorHelper({ authToken, authUser, onLogout }: { authToken: string; authUser: AuthUser; onLogout: () => void }) {
  const { width } = useWindowDimensions();
  const [page, setPage] = useState<AppPage>('dashboard');
  const [diagnosisStep, setDiagnosisStep] = useState<DiagnosisStep>('upload');
  const [selectedMachine, setSelectedMachine] = useState<Machine | null>(null);
  const [selectedImage, setSelectedImage] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [imageQuality, setImageQuality] = useState<ImageQuality | null>(null);
  const [imageValidationPending, setImageValidationPending] = useState(false);
  const [diagnosisId, setDiagnosisId] = useState<string | null>(null);
  const [autoAdvancedDiagnosisId, setAutoAdvancedDiagnosisId] = useState<string | null>(null);
  const [confirmRows, setConfirmRows] = useState<ConfirmCodeRow[]>(() => [createConfirmRow()]);
  const [moduleKey, setModuleKey] = useState('PLUGSA');
  const [manualResult, setManualResult] = useState<DiagnosisDetail | null>(null);
  const [selectedHistoryItem, setSelectedHistoryItem] = useState<DiagnosisHistoryItem | null>(null);
  const [addMachineModalOpen, setAddMachineModalOpen] = useState(false);
  const [machineSearch, setMachineSearch] = useState('');
  const [debouncedMachineSearch, setDebouncedMachineSearch] = useState('');
  const fade = useRef(new Animated.Value(1)).current;
  const trimmedMachineSearch = machineSearch.trim();
  const userMachinesQueryKey = useMemo(() => ['user-machines', authToken] as const, [authToken]);
  const diagnosisHistoryQueryKey = useMemo(() => ['diagnosis-history', authToken] as const, [authToken]);

  useEffect(() => {
    fade.setValue(0);
    Animated.timing(fade, {
      toValue: 1,
      duration: 220,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [diagnosisStep, fade, page]);

  const machines = useQuery({
    enabled: addMachineModalOpen,
    placeholderData: (previousData) => previousData,
    queryKey: ['machines', authToken, debouncedMachineSearch],
    queryFn: () => fetchMachines(authToken, debouncedMachineSearch),
  });

  const userMachinesQuery = useQuery({
    queryKey: userMachinesQueryKey,
    queryFn: () => fetchUserMachines(authToken),
  });

  const diagnosis = useQuery({
    enabled: !!diagnosisId,
    queryKey: ['diagnosis', diagnosisId, authToken],
    queryFn: () => fetchDiagnosis(diagnosisId as string, authToken),
    refetchInterval: (query) => {
      const status = query.state.data?.status;

      return status === 'resolved' || status === 'needs_confirmation' || status === 'failed' ? false : 2500;
    },
  });

  const diagnosisHistoryQuery = useQuery({
    queryKey: diagnosisHistoryQueryKey,
    queryFn: () => fetchDiagnosisHistory(authToken),
  });

  const upload = useMutation({
    mutationFn: (input: { machineId: number; asset: ImagePicker.ImagePickerAsset }) => uploadDiagnosis({ ...input, token: authToken }),
    onSuccess: (payload) => {
      setDiagnosisId(payload.id);
      setAutoAdvancedDiagnosisId(null);
      setManualResult(null);
      void queryClient.invalidateQueries({ queryKey: diagnosisHistoryQueryKey });
    },
  });

  const manualLookup = useMutation({
    mutationFn: (input: { diagnosisId: string; entries: Array<{ code: string; dashboard_color_meaning_id: number | null }>; moduleKey: string }) => submitManualCode({ ...input, token: authToken }),
    onSuccess: (payload) => {
      setManualResult(payload);
      queryClient.setQueryData(['diagnosis', payload.id, authToken], payload);
      void queryClient.invalidateQueries({ queryKey: diagnosisHistoryQueryKey });
      setPage('diagnosis');
      setDiagnosisStep('result');
    },
  });

  const addUserMachine = useMutation({
    mutationFn: (machine: Machine) => attachUserMachine({ machineId: machine.id, token: authToken }),
    onSuccess: (machine) => {
      queryClient.setQueryData<Machine[]>(userMachinesQueryKey, (current = []) =>
        sortMachinesByName([...current.filter((candidate) => candidate.id !== machine.id), machine]),
      );
      queryClient.setQueriesData<Machine[]>(
        { queryKey: ['machines', authToken] },
        (current) => current?.filter((candidate) => candidate.id !== machine.id) ?? current,
      );

      setSelectedMachine((current) => {
        if (current) {
          return current;
        }

        storeSelectedMachineId(authUser.id, machine.id);

        return machine;
      });
      setAddMachineModalOpen(false);
      setMachineSearch('');
      setDebouncedMachineSearch('');
      void queryClient.invalidateQueries({ queryKey: userMachinesQueryKey });
      void queryClient.invalidateQueries({ queryKey: ['machines', authToken] });
    },
  });

  const removeUserMachine = useMutation({
    mutationFn: (machine: Machine) => detachUserMachine({ machineId: machine.id, token: authToken }),
    onMutate: (machine) => {
      const currentUserMachines = queryClient.getQueryData<Machine[]>(userMachinesQueryKey) ?? [];
      const nextUserMachines = currentUserMachines.filter((candidate) => candidate.id !== machine.id);

      queryClient.setQueryData<Machine[]>(userMachinesQueryKey, nextUserMachines);
      queryClient.setQueriesData<Machine[]>(
        { queryKey: ['machines', authToken] },
        (current = []) => sortMachinesByName([...current.filter((candidate) => candidate.id !== machine.id), machine]),
      );

      setSelectedMachine((current) => {
        if (current?.id !== machine.id) {
          return current;
        }

        const nextSelectedMachine = nextUserMachines[0] ?? null;

        if (nextSelectedMachine) {
          storeSelectedMachineId(authUser.id, nextSelectedMachine.id);
        } else {
          forgetSelectedMachineId(authUser.id);
        }

        return nextSelectedMachine;
      });
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey: userMachinesQueryKey });
      void queryClient.invalidateQueries({ queryKey: ['machines', authToken] });
    },
  });

  const activeDiagnosis = manualResult ?? diagnosis.data ?? null;
  const confirmEntries = confirmEntriesFromRows(confirmRows);
  const colorMeanings = colorMeaningsForMachine(activeDiagnosis?.machine ?? selectedMachine ?? null);
  const canAnalyze = !!selectedMachine && !!selectedImage && imageQuality?.status !== 'fail' && !upload.isPending && !imageValidationPending;
  const canConfirm = !!diagnosisId && confirmEntries.length > 0 && !manualLookup.isPending;
  const translateY = fade.interpolate({ inputRange: [0, 1], outputRange: [14, 0] });
  const machineDetails = selectedMachine ? [selectedMachine.manufacturer, selectedMachine.model_number].filter(Boolean).join(' ') || 'No model details' : null;
  const confirmScreenshotSource = useMemo<ScreenshotPreviewSource | null>(() => {
    if (selectedImage) {
      return {
        height: selectedImage.height ?? null,
        uri: selectedImage.uri,
        width: selectedImage.width ?? null,
      };
    }

    if (activeDiagnosis?.screenshot_url) {
      return {
        uri: resolveAssetUrl(activeDiagnosis.screenshot_url),
      };
    }

    return null;
  }, [activeDiagnosis?.screenshot_url, selectedImage]);
  const userMachines = userMachinesQuery.data ?? [];
  const diagnosisHistory = diagnosisHistoryQuery.data ?? [];
  const userMachineIds = useMemo(() => userMachines.map((machine) => machine.id), [userMachines]);
  const addableMachines = useMemo(
    () => (machines.data ?? []).filter((machine) => !userMachineIds.includes(machine.id)),
    [machines.data, userMachineIds],
  );
  const machineMutationError =
    removeUserMachine.error instanceof Error
      ? removeUserMachine.error.message
      : null;
  const addMachineError = addUserMachine.error instanceof Error ? addUserMachine.error.message : null;
  const diagnosisHistoryError = diagnosisHistoryQuery.error instanceof Error ? diagnosisHistoryQuery.error.message : null;
  const recentDashboardHistory = useMemo(() => diagnosisHistory.slice(0, 4), [diagnosisHistory]);
  const shellStyle = useMemo(
    () => [styles.shell, width >= 560 ? styles.dashboardShell : null],
    [width],
  );
  const stepCardStyle = useMemo(
    () => [styles.stepCard, styles.dashboardStepCard],
    [],
  );
  const currentPageLabel = useMemo(() => {
    if (page === 'machines') {
      return 'Machines';
    }

    if (page === 'history') {
      return 'Scan history';
    }

    if (page === 'history-detail') {
      return 'Scan overview';
    }

    if (page === 'diagnosis') {
      switch (diagnosisStep) {
        case 'confirm':
          return 'Confirm codes';
        case 'result':
          return 'Error preview';
        default:
          return 'Scan';
      }
    }

    return 'Dashboard';
  }, [diagnosisStep, page]);

  useEffect(() => {
    if (
      (machines.error instanceof ApiError && [401, 403].includes(machines.error.status)) ||
      (userMachinesQuery.error instanceof ApiError && [401, 403].includes(userMachinesQuery.error.status)) ||
      (diagnosis.error instanceof ApiError && [401, 403].includes(diagnosis.error.status)) ||
      (diagnosisHistoryQuery.error instanceof ApiError && [401, 403].includes(diagnosisHistoryQuery.error.status))
    ) {
      onLogout();
    }
  }, [diagnosis.error, diagnosisHistoryQuery.error, machines.error, onLogout, userMachinesQuery.error]);

  useEffect(() => {
    if (page !== 'diagnosis' || diagnosisStep !== 'upload' || !diagnosis.data || autoAdvancedDiagnosisId === diagnosis.data.id) {
      return;
    }

    const detail = diagnosis.data;

    if (detail.status === 'processing' || detail.status === 'uploaded') {
      return;
    }

    setModuleKey(moduleFromDiagnosis(detail));
    setConfirmRows(rowsFromCandidates(detail.candidates ?? []));
    setAutoAdvancedDiagnosisId(detail.id);
    setDiagnosisStep('confirm');
  }, [autoAdvancedDiagnosisId, diagnosis.data, diagnosisStep, page]);

  useEffect(() => {
    if (selectedMachine || !userMachines.length) {
      return;
    }

    const storedMachineId = readStoredMachineId(authUser.id);
    const storedMachine = userMachines.find((machine) => machine.id === storedMachineId) ?? userMachines[0];

    if (storedMachine) {
      setSelectedMachine(storedMachine);
      storeSelectedMachineId(authUser.id, storedMachine.id);
    }
  }, [authUser.id, selectedMachine, userMachines]);

  useEffect(() => {
    if (!selectedMachine || !userMachinesQuery.isSuccess || userMachines.some((machine) => machine.id === selectedMachine.id)) {
      return;
    }

    const nextSelectedMachine = userMachines[0] ?? null;

    setSelectedMachine(nextSelectedMachine);

    if (nextSelectedMachine) {
      storeSelectedMachineId(authUser.id, nextSelectedMachine.id);
    } else {
      forgetSelectedMachineId(authUser.id);
    }
  }, [authUser.id, selectedMachine, userMachines, userMachinesQuery.isSuccess]);

  useEffect(() => {
    if (!addMachineModalOpen) {
      setDebouncedMachineSearch('');

      return;
    }

    const timeout = setTimeout(() => {
      setDebouncedMachineSearch(trimmedMachineSearch);
    }, 220);

    return () => clearTimeout(timeout);
  }, [addMachineModalOpen, trimmedMachineSearch]);

  async function chooseImage(source: ImageSource): Promise<void> {
    const asset = await pickImage(source);

    if (!asset) {
      return;
    }

    setSelectedImage(asset);
    setImageQuality(null);
    setDiagnosisId(null);
    setAutoAdvancedDiagnosisId(null);
    setManualResult(null);
    setImageValidationPending(true);

    try {
      setImageQuality(await validateImageQuality(asset));
    } finally {
      setImageValidationPending(false);
    }
  }

  function startOver(): void {
    setPage('dashboard');
    setDiagnosisStep('upload');
    resetDiagnosisState();
  }

  function resetDiagnosisState(): void {
    setSelectedImage(null);
    setImageQuality(null);
    setDiagnosisId(null);
    setAutoAdvancedDiagnosisId(null);
    setManualResult(null);
    setConfirmRows([createConfirmRow()]);
    setModuleKey('PLUGSA');
  }

  function updateConfirmRow(rowId: string, patch: Partial<Omit<ConfirmCodeRow, 'id'>>): void {
    setConfirmRows((current) => current.map((row) => (row.id === rowId ? { ...row, ...patch } : row)));
  }

  function addConfirmRow(): void {
    setConfirmRows((current) => [...current, createConfirmRow('', colorMeanings[0]?.id ?? null)]);
  }

  function removeConfirmRow(rowId: string): void {
    setConfirmRows((current) => {
      const nextRows = current.filter((row) => row.id !== rowId);

      return nextRows.length > 0 ? nextRows : [createConfirmRow()];
    });
  }

  function selectMachine(machine: Machine): void {
    setSelectedMachine(machine);
    storeSelectedMachineId(authUser.id, machine.id);
  }

  function addMachineToUserList(machine: Machine): void {
    addUserMachine.mutate(machine);
  }

  function removeMachineFromUserList(machine: Machine): void {
    removeUserMachine.mutate(machine);
  }

  function beginScan(): void {
    resetDiagnosisState();
    setSelectedHistoryItem(null);
    setDiagnosisStep('upload');
    setPage(selectedMachine ? 'diagnosis' : 'machines');
  }

  function openDiagnosisHistory(): void {
    setSelectedHistoryItem(null);
    setPage('history');
  }

  function openDiagnosisHistoryItem(item: DiagnosisHistoryItem): void {
    setSelectedImage(null);
    setImageQuality(null);
    setManualResult(null);
    setDiagnosisId(item.id);
    setSelectedHistoryItem(item);
    setAutoAdvancedDiagnosisId(null);
    setPage('history-detail');

    const historyMachine = item.machine ? userMachines.find((machine) => machine.id === item.machine?.id) ?? null : null;

    if (historyMachine) {
      setSelectedMachine(historyMachine);
      storeSelectedMachineId(authUser.id, historyMachine.id);
    }
  }

  function closeAddMachineModal(): void {
    setAddMachineModalOpen(false);
    setMachineSearch('');
    addUserMachine.reset();
  }

  return (
    <SafeAreaView style={styles.screen}>
      <AmbientBackground />
      <StatusBar style="light" />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.keyboard}>
        <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled">
          <View style={shellStyle}>
            <View style={styles.appWindow}>
              <View style={styles.appToolbar}>
                <View style={styles.appToolbarCopy}>
                  <Text style={styles.appToolbarLabel}>Machine Error Helper</Text>
                  <Text style={styles.appToolbarTitle}>{currentPageLabel}</Text>
                </View>
                <Pressable onPress={onLogout} style={({ pressed }) => [styles.logoutButton, pressed && styles.buttonPressed]}>
                  <Text style={styles.logoutText}>Logout</Text>
                </Pressable>
              </View>

              {page === 'diagnosis' ? <Stepper currentStep={diagnosisStep} /> : null}

              <Animated.View style={[stepCardStyle, { opacity: fade, transform: [{ translateY }] }]}>
              {page === 'dashboard' ? (
                <View style={styles.dashboardScreen}>
                  <View style={styles.dashboardHeroCard}>
                    <View style={styles.dashboardHeroCopy}>
                      <Text style={styles.dashboardHeroLabel}>Active machine</Text>
                      <Text numberOfLines={2} style={styles.dashboardHeroTitle}>
                        {selectedMachine ? selectedMachine.name : 'Choose a machine'}
                      </Text>
                      <Text numberOfLines={1} style={styles.dashboardHeroMeta}>
                        {selectedMachine ? machineDetails ?? 'Ready to scan' : 'Required before scanning'}
                      </Text>
                    </View>
                    <Text style={[styles.dashboardHeroBadge, selectedMachine && styles.dashboardHeroBadgeActive]}>
                      {selectedMachine ? 'Ready' : 'Select'}
                    </Text>
                  </View>

                  <View style={styles.dashboardActionGrid}>
                    <DashboardActionTile
                      hint={selectedMachine ? 'Open scan flow' : 'Select a machine'}
                      label="Scan"
                      meta={selectedMachine ? 'Take photo or upload' : 'Choose machine first'}
                      onPress={beginScan}
                      tone="primary"
                      value={selectedMachine ? 'Start' : 'Select'}
                    />
                    <DashboardActionTile
                      hint="Manage machines"
                      label="Machines"
                      meta={selectedMachine ? 'Active machine saved' : 'Manage machine list'}
                      onPress={() => setPage('machines')}
                      value={userMachinesQuery.isLoading ? '...' : String(userMachines.length)}
                    />
                    <DashboardActionTile
                      fullWidth
                      hint="Review scans"
                      label="History"
                      meta="All scans"
                      onPress={openDiagnosisHistory}
                      value={diagnosisHistoryQuery.isLoading ? '...' : String(diagnosisHistory.length)}
                    />
                  </View>

                  <View style={styles.dashboardSection}>
                    <View style={styles.dashboardSectionHeader}>
                      <Text style={styles.dashboardSectionTitle}>Recent scans</Text>
                      {diagnosisHistory.length > 0 ? (
                        <Pressable onPress={openDiagnosisHistory} style={({ pressed }) => [styles.dashboardInlineLink, pressed && styles.buttonPressed]}>
                          <Text style={styles.dashboardInlineLinkText}>All</Text>
                        </Pressable>
                      ) : null}
                    </View>

                    {diagnosisHistoryQuery.isLoading ? (
                      <View style={styles.dashboardStateBox}>
                        <ActivityIndicator color="#8fb7ff" />
                        <Text style={styles.dashboardStateText}>Loading</Text>
                      </View>
                    ) : recentDashboardHistory.length > 0 ? (
                      <View style={styles.dashboardList}>
                        {recentDashboardHistory.map((item) => (
                          <DashboardHistoryRow key={item.id} item={item} onPress={openDiagnosisHistoryItem} />
                        ))}
                      </View>
                    ) : (
                      <View style={styles.dashboardStateBox}>
                        <Text style={styles.dashboardStateText}>No scans yet</Text>
                      </View>
                    )}
                  </View>
                </View>
              ) : null}

              {page === 'machines' ? (
                <View style={styles.machinesScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>My machines</Text>
                    <Pressable onPress={() => setPage('dashboard')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to Dashboard</Text>
                    </Pressable>
                  </View>

                  <View style={styles.machinesHeroCard}>
                    <View style={styles.machinesHeroCopy}>
                      <Text style={styles.dashboardHeroLabel}>Active machine</Text>
                      <Text numberOfLines={2} style={styles.machinesHeroTitle}>
                        {selectedMachine ? selectedMachine.name : 'No machine selected'}
                      </Text>
                      <Text numberOfLines={1} style={styles.machinesHeroMeta}>
                        {selectedMachine ? machineDetails ?? 'Ready to scan' : 'Choose one machine for scanning'}
                      </Text>
                    </View>
                    <Text style={[styles.dashboardHeroBadge, selectedMachine && styles.dashboardHeroBadgeActive]}>
                      {userMachinesQuery.isLoading ? '...' : `${userMachines.length}`}
                    </Text>
                  </View>

                  <Pressable onPress={() => setAddMachineModalOpen(true)} style={({ pressed }) => [styles.addMachineButton, pressed && styles.buttonPressed]}>
                    <Text style={styles.buttonText}>Add machine</Text>
                  </Pressable>

                  {userMachinesQuery.isLoading ? (
                    <View style={styles.dashboardStateBox}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.dashboardStateText}>Loading machines</Text>
                    </View>
                  ) : userMachinesQuery.error ? (
                    <View style={styles.dashboardStateBox}>
                      <Text style={styles.error}>Backend is not reachable at {apiBaseUrl}</Text>
                    </View>
                  ) : (
                    <View style={styles.dashboardSection}>
                      <View style={styles.dashboardSectionHeader}>
                        <Text style={styles.dashboardSectionTitle}>Saved machines</Text>
                        <Text style={styles.machinesSectionBadge}>{userMachines.length}</Text>
                      </View>
                      {userMachines.length > 0 ? (
                        <View style={styles.machineList}>
                          {userMachines.map((machine) => {
                            const selected = selectedMachine?.id === machine.id;

                            return (
                              <View key={machine.id} style={[styles.machineRow, selected && styles.machineRowSelected]}>
                                <View style={styles.machineRowTop}>
                                  <View style={styles.machineInfo}>
                                    <Text style={styles.machineName}>{machine.name}</Text>
                                    <Text style={styles.machineMeta}>
                                      {[machine.manufacturer, machine.model_number].filter(Boolean).join(' · ') || 'No model details'}
                                    </Text>
                                  </View>
                                  <Text style={[styles.machineStateBadge, selected && styles.machineStateBadgeActive]}>
                                    {selected ? 'Active' : 'Saved'}
                                  </Text>
                                </View>
                                <View style={styles.machineRowActions}>
                                  <Pressable
                                    onPress={() => selectMachine(machine)}
                                    style={({ pressed }) => [
                                      styles.machineActionButton,
                                      selected && styles.machineActionButtonActive,
                                      pressed && styles.buttonPressed,
                                    ]}
                                  >
                                    <Text style={[styles.machineActionButtonText, selected && styles.machineActionButtonTextActive]}>
                                      {selected ? 'Using now' : 'Use machine'}
                                    </Text>
                                  </Pressable>
                                  <Pressable
                                    disabled={removeUserMachine.isPending}
                                    onPress={() => removeMachineFromUserList(machine)}
                                    style={({ pressed }) => [
                                      styles.machineActionButton,
                                      styles.machineActionButtonDanger,
                                      removeUserMachine.isPending && styles.buttonDisabled,
                                      pressed && styles.buttonPressed,
                                    ]}
                                  >
                                    <Text style={styles.machineActionButtonText}>{removeUserMachine.isPending ? 'Removing' : 'Remove'}</Text>
                                  </Pressable>
                                </View>
                              </View>
                            );
                          })}
                        </View>
                      ) : (
                        <View style={styles.dashboardStateBox}>
                          <Text style={styles.dashboardStateText}>No saved machines yet</Text>
                        </View>
                      )}

                      {machineMutationError ? <Text style={styles.error}>{machineMutationError}</Text> : null}
                    </View>
                  )}

                </View>
              ) : null}

              {page === 'history' ? (
                <View style={styles.contentScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>Scan history</Text>
                    <Pressable onPress={() => setPage('dashboard')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to Dashboard</Text>
                    </Pressable>
                  </View>

                  {diagnosisHistoryQuery.isLoading ? (
                    <View style={styles.stateBox}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.stateText}>Loading scan history</Text>
                    </View>
                  ) : diagnosisHistoryQuery.error ? (
                    <View style={styles.stateBox}>
                      <Text style={styles.error}>{diagnosisHistoryError ?? 'Could not load scan history.'}</Text>
                    </View>
                  ) : diagnosisHistory.length > 0 ? (
                    <View style={styles.scanHistoryList}>
                      {diagnosisHistory.map((item) => (
                        <Pressable
                          accessibilityHint="Open scan overview"
                          accessibilityRole="button"
                          key={item.id}
                          onPress={() => openDiagnosisHistoryItem(item)}
                          style={({ pressed }) => [styles.scanHistoryRow, pressed && styles.buttonPressed]}
                        >
                          <View style={styles.scanHistoryRowHeader}>
                            <View style={styles.scanHistoryCopy}>
                              <Text style={styles.scanHistoryMachine}>{item.machine?.name ?? 'Unknown machine'}</Text>
                            </View>
                          </View>
                          <HistoryCodeChips chips={historyCodeChips(item)} />
                          {historyMatchLabel(item) ? <Text style={styles.scanHistoryMatch}>{historyMatchLabel(item)}</Text> : null}
                          <View style={styles.scanHistoryRowFooter}>
                            <Text numberOfLines={1} style={styles.scanHistoryMeta}>
                              {formatHistoryTimestamp(item.created_at)}
                            </Text>
                            <PressableCue label="Open scan" />
                          </View>
                        </Pressable>
                      ))}
                    </View>
                  ) : (
                    <View style={styles.stateBox}>
                      <Text style={styles.stateText}>No scans yet. Once you analyze a screenshot, it will appear here.</Text>
                    </View>
                  )}
                </View>
              ) : null}

              {page === 'diagnosis' && diagnosisStep === 'upload' ? (
                <View style={styles.contentScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>Upload screenshot</Text>
                    <Pressable onPress={() => setPage('dashboard')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to Dashboard</Text>
                    </Pressable>
                  </View>

                  {selectedImage ? (
                    <RNImage source={{ uri: selectedImage.uri }} resizeMode="cover" style={styles.previewImage} />
                  ) : (
                    <View style={styles.previewEmpty}>
                      <Text style={styles.previewText}>No screenshot selected</Text>
                    </View>
                  )}

                  <View style={styles.actionRow}>
                    <Pressable
                      onPress={() => chooseImage('camera')}
                      style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}
                    >
                      <Text style={styles.buttonText}>Take Photo</Text>
                    </Pressable>

                    <Pressable
                      onPress={() => chooseImage('library')}
                      style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}
                    >
                      <Text style={styles.secondaryButtonText}>Upload File</Text>
                    </Pressable>
                  </View>

                  {imageValidationPending ? (
                    <View style={styles.inlineState}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.inlineStateText}>Checking image quality</Text>
                    </View>
                  ) : null}

                  <QualityBadge quality={imageQuality} />

                  {upload.isPending || diagnosis.data?.status === 'processing' || diagnosis.data?.status === 'uploaded' ? (
                    <View style={styles.inlineState}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.inlineStateText}>Extracting visible codes</Text>
                    </View>
                  ) : null}

                  {upload.error ? <Text style={styles.error}>{upload.error.message}</Text> : null}
                  {diagnosis.error ? <Text style={styles.error}>{diagnosis.error.message}</Text> : null}

                  <View style={styles.navRow}>
                    <Pressable
                      disabled={!canAnalyze}
                      onPress={() => selectedMachine && selectedImage && upload.mutate({ machineId: selectedMachine.id, asset: selectedImage })}
                      style={({ pressed }) => [styles.button, !canAnalyze && styles.buttonDisabled, pressed && styles.buttonPressed]}
                    >
                      <Text style={styles.buttonText}>{upload.isPending ? 'Analyzing' : 'Analyze Screenshot'}</Text>
                    </Pressable>
                  </View>
                </View>
              ) : null}

              {page === 'diagnosis' && diagnosisStep === 'confirm' ? (
                <View style={styles.contentScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>Confirm detected codes</Text>
                    <Pressable onPress={() => setPage('dashboard')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to Dashboard</Text>
                    </Pressable>
                  </View>

                  {confirmScreenshotSource ? <ScreenshotViewer source={confirmScreenshotSource} /> : null}

                  <View style={styles.confirmNudgeBox}>
                    <Text style={styles.confirmNudgeTitle}>Check the extracted codes</Text>
                    <Text style={styles.confirmNudgeText}>Review each row against the screenshot. Edit the code or color status before continuing.</Text>
                  </View>

                  <View style={[styles.inputGrid, styles.confirmInputGrid]}>
                    <View style={styles.inputGroup}>
                      <Text style={styles.inputLabel}>Module</Text>
                      <TextInput
                        autoCapitalize="characters"
                        onChangeText={setModuleKey}
                        placeholder="PLUGSA"
                        placeholderTextColor="#7f8490"
                        style={styles.input}
                        value={moduleKey}
                      />
                    </View>

                    <View style={styles.inputGroup}>
                      <View style={styles.confirmRowsHeader}>
                        <Text style={styles.inputLabel}>Codes</Text>
                        <Pressable onPress={addConfirmRow} style={({ pressed }) => [styles.smallActionButton, pressed && styles.buttonPressed]}>
                          <Text style={styles.smallActionButtonText}>Add row</Text>
                        </Pressable>
                      </View>
                      <View style={styles.confirmRows}>
                        {confirmRows.map((row, index) => (
                          <View key={row.id} style={styles.confirmRow}>
                            <View style={styles.confirmRowTop}>
                              <TextInput
                                autoCapitalize="characters"
                                onChangeText={(value) => updateConfirmRow(row.id, { code: value })}
                                placeholder={`Code ${index + 1}`}
                                placeholderTextColor="#7f8490"
                                style={[styles.input, styles.confirmCodeInput]}
                                value={row.code}
                              />
                              <Pressable
                                accessibilityLabel="Remove code row"
                                disabled={confirmRows.length === 1 && row.code.trim() === ''}
                                onPress={() => removeConfirmRow(row.id)}
                                style={({ pressed }) => [
                                  styles.removeRowButton,
                                  confirmRows.length === 1 && row.code.trim() === '' && styles.buttonDisabled,
                                  pressed && styles.buttonPressed,
                                ]}
                              >
                                <Text style={styles.removeRowButtonText}>x</Text>
                              </Pressable>
                            </View>
                            <View style={styles.colorChoiceRow}>
                              <Pressable
                                onPress={() => updateConfirmRow(row.id, { colorMeaningId: null })}
                                style={({ pressed }) => [
                                  styles.colorChoice,
                                  row.colorMeaningId === null && styles.colorChoiceSelected,
                                  pressed && styles.buttonPressed,
                                ]}
                              >
                                <View style={[styles.colorChoiceSwatch, styles.colorChoiceSwatchUnknown]} />
                                <Text style={[styles.colorChoiceText, row.colorMeaningId === null && styles.colorChoiceTextSelected]}>Unknown</Text>
                              </Pressable>
                              {colorMeanings.map((meaning) => (
                                <Pressable
                                  key={meaning.id}
                                  onPress={() => updateConfirmRow(row.id, { colorMeaningId: meaning.id })}
                                  style={({ pressed }) => [
                                    styles.colorChoice,
                                    row.colorMeaningId === meaning.id && styles.colorChoiceSelected,
                                    pressed && styles.buttonPressed,
                                  ]}
                                >
                                  <View style={[styles.colorChoiceSwatch, { backgroundColor: meaning.hex_color }]} />
                                  <Text style={[styles.colorChoiceText, row.colorMeaningId === meaning.id && styles.colorChoiceTextSelected]}>
                                    {meaning.label}
                                  </Text>
                                </Pressable>
                              ))}
                            </View>
                          </View>
                        ))}
                      </View>
                    </View>
                  </View>

                  {manualLookup.error ? <Text style={styles.error}>{manualLookup.error.message}</Text> : null}

                  <View style={styles.confirmNavRow}>
                    <Pressable onPress={() => setDiagnosisStep('upload')} style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.secondaryButtonText}>Back</Text>
                    </Pressable>
                    <Pressable
                      disabled={!canConfirm}
                      onPress={() =>
                        diagnosisId &&
                        manualLookup.mutate({
                          diagnosisId,
                          entries: confirmEntries,
                          moduleKey: moduleKey.trim(),
                        })
                      }
                      style={({ pressed }) => [styles.button, !canConfirm && styles.buttonDisabled, pressed && styles.buttonPressed]}
                    >
                      <Text style={styles.buttonText}>{manualLookup.isPending ? 'Checking Codes' : 'Confirm Codes'}</Text>
                    </Pressable>
                  </View>
                </View>
              ) : null}

              {page === 'diagnosis' && diagnosisStep === 'result' ? (
                <View style={styles.contentScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>Error preview</Text>
                    <Pressable onPress={() => setPage('dashboard')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to Dashboard</Text>
                    </Pressable>
                  </View>
                  <ResultPanel detail={activeDiagnosis} />

                  <View style={styles.navRow}>
                    <Pressable onPress={() => setDiagnosisStep('confirm')} style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.secondaryButtonText}>Edit Codes</Text>
                    </Pressable>
                    <Pressable onPress={startOver} style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}>
                      <Text style={styles.buttonText}>New Diagnosis</Text>
                    </Pressable>
                  </View>
                </View>
              ) : null}

              {page === 'history-detail' ? (
                <View style={styles.contentScreen}>
                  <View style={styles.pageTitleRow}>
                    <Text style={[styles.sectionTitle, styles.pageTitle]}>Scan overview</Text>
                    <Pressable onPress={() => setPage('history')} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.pageBackText}>Back to History</Text>
                    </Pressable>
                  </View>

                  {diagnosis.isLoading && selectedHistoryItem ? (
                    <View style={styles.stateBox}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.stateText}>Loading scan details</Text>
                    </View>
                  ) : null}

                  {diagnosis.error && selectedHistoryItem ? (
                    <View style={styles.stateBox}>
                      <Text style={styles.error}>{diagnosis.error.message}</Text>
                    </View>
                  ) : null}

                  {diagnosis.isLoading && !selectedHistoryItem ? (
                    <View style={styles.stateBox}>
                      <ActivityIndicator color="#8fb7ff" />
                      <Text style={styles.stateText}>Loading scan overview</Text>
                    </View>
                  ) : diagnosis.error && !selectedHistoryItem ? (
                    <View style={styles.stateBox}>
                      <Text style={styles.error}>{diagnosis.error.message}</Text>
                    </View>
                  ) : (
                    <HistoryOverviewPanel detail={activeDiagnosis} item={selectedHistoryItem} />
                  )}
                </View>
              ) : null}
              </Animated.View>
            </View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
      <Modal animationType="fade" onRequestClose={closeAddMachineModal} transparent visible={addMachineModalOpen}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalPanel}>
            <View style={styles.pageTitleRow}>
              <Text style={[styles.sectionTitle, styles.pageTitle]}>Add machine</Text>
              <Pressable onPress={closeAddMachineModal} style={({ pressed }) => [styles.pageBackButton, pressed && styles.buttonPressed]}>
                <Text style={styles.pageBackText}>Close</Text>
              </Pressable>
            </View>

            <View style={styles.modalInputGroup}>
              <Text style={styles.inputLabel}>Search catalog</Text>
              <TextInput
                autoCapitalize="none"
                autoFocus={addMachineModalOpen}
                onChangeText={setMachineSearch}
                placeholder="Start typing a machine name"
                placeholderTextColor="#7f8490"
                style={styles.input}
                value={machineSearch}
              />
            </View>

            <View style={styles.modalResultArea}>
              {machines.isLoading && !machines.data ? (
                <View style={[styles.modalMessageBox, styles.modalLoadingBox]}>
                  <ActivityIndicator color="#8fb7ff" />
                  <Text style={styles.modalMessageText}>Searching machines</Text>
                </View>
              ) : machines.error ? (
                <View style={styles.modalMessageBox}>
                  <Text style={styles.error}>Machine search is not reachable at {apiBaseUrl}</Text>
                </View>
              ) : addableMachines.length > 0 ? (
                <View style={styles.modalResultStack}>
                  <ScrollView
                    contentContainerStyle={styles.modalResultList}
                    keyboardShouldPersistTaps="handled"
                    style={[styles.modalResultViewport, machines.isFetching && styles.modalResultViewportLoading]}
                  >
                    {addableMachines.map((machine) => (
                      <View key={machine.id} style={styles.machineRow}>
                        <View style={styles.machineRowTop}>
                          <View style={styles.machineInfo}>
                            <Text style={styles.machineName}>{machine.name}</Text>
                            <Text style={styles.machineMeta}>
                              {[machine.manufacturer, machine.model_number].filter(Boolean).join(' · ') || 'No model details'}
                            </Text>
                          </View>
                        </View>
                        <Pressable
                          disabled={addUserMachine.isPending}
                          onPress={() => addMachineToUserList(machine)}
                          style={({ pressed }) => [styles.machineActionButton, addUserMachine.isPending && styles.buttonDisabled, pressed && styles.buttonPressed]}
                        >
                          <Text style={styles.machineActionButtonText}>{addUserMachine.isPending ? 'Adding' : 'Add machine'}</Text>
                        </Pressable>
                      </View>
                    ))}
                  </ScrollView>
                  {machines.isFetching ? (
                    <View pointerEvents="none" style={styles.modalLoadingOverlay}>
                      <ActivityIndicator color="#8fb7ff" />
                    </View>
                  ) : null}
                </View>
              ) : (
                <View style={styles.modalMessageBox}>
                  <Text style={styles.modalMessageText}>
                    {(machines.data ?? []).length > 0 ? 'All matching machines are already saved.' : 'No matching machines found.'}
                  </Text>
                </View>
              )}
            </View>

            {addMachineError ? <Text style={styles.error}>{addMachineError}</Text> : null}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

export default function App() {
  const [authToken, setAuthToken] = useState<string | null>(null);
  const [authUser, setAuthUser] = useState<AuthUser | null>(null);
  const [authChecking, setAuthChecking] = useState(true);
  const [authError, setAuthError] = useState<string | null>(null);
  const [loginPending, setLoginPending] = useState(false);

  const logout = useCallback(async () => {
    const token = authToken;

    setAuthToken(null);
    setAuthUser(null);
    setAuthError(null);
    queryClient.clear();
    await forgetAuthToken();

    if (token) {
      await logoutFromApi(token);
    }
  }, [authToken]);

  useEffect(() => {
    let cancelled = false;

    async function restoreSession(): Promise<void> {
      const token = await readStoredAuthToken();

      if (!token) {
        if (!cancelled) {
          setAuthChecking(false);
        }

        return;
      }

      try {
        const user = await fetchCurrentUser(token);

        if (!cancelled) {
          setAuthToken(token);
          setAuthUser(user);
        }
      } catch {
        await forgetAuthToken();
      } finally {
        if (!cancelled) {
          setAuthChecking(false);
        }
      }
    }

    restoreSession();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleLogin(input: { email: string; password: string }): Promise<void> {
    setLoginPending(true);
    setAuthError(null);

    try {
      const session = await loginToApi(input);

      await storeAuthToken(session.token);
      setAuthToken(session.token);
      setAuthUser(session.user);
      queryClient.clear();
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Could not log in.');
    } finally {
      setLoginPending(false);
    }
  }

  async function handleRegister(input: { name: string; email: string; password: string; passwordConfirmation: string }): Promise<void> {
    setLoginPending(true);
    setAuthError(null);

    try {
      const session = await registerWithApi(input);

      await storeAuthToken(session.token);
      setAuthToken(session.token);
      setAuthUser(session.user);
      queryClient.clear();
    } catch (error) {
      setAuthError(error instanceof Error ? error.message : 'Could not create account.');
    } finally {
      setLoginPending(false);
    }
  }

  if (authChecking) {
    return (
      <SafeAreaView style={styles.screen}>
        <AmbientBackground />
        <StatusBar style="light" />
        <View style={styles.bootScreen}>
          <ActivityIndicator color="#8fb7ff" />
          <Text style={styles.stateText}>Checking session</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <QueryClientProvider client={queryClient}>
      {authToken && authUser ? (
        <MachineErrorHelper authToken={authToken} authUser={authUser} onLogout={logout} />
      ) : (
        <LoginScreen error={authError} isPending={loginPending} onLogin={handleLogin} onRegister={handleRegister} />
      )}
    </QueryClientProvider>
  );
}

const styles = StyleSheet.create({
  screen: {
    backgroundColor: '#07080e',
    flex: 1,
    overflow: 'hidden',
  },
  ambientRoot: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#020307',
  },
  backgroundBase: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#020307',
  },
  halftoneLayer: {
    position: 'absolute',
  },
  dotRow: {
    flexDirection: 'row',
  },
  dotCell: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  dot: {
    backgroundColor: 'rgba(255, 255, 255, 0.72)',
    borderRadius: 8,
    height: 2.4,
    shadowColor: '#ffffff',
    shadowOffset: { height: 0, width: 0 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    width: 2.4,
  },
  dotDim: {
    backgroundColor: 'rgba(214, 224, 238, 0.76)',
  },
  dotMid: {
    backgroundColor: 'rgba(244, 246, 248, 0.84)',
    height: 2.8,
    width: 2.8,
  },
  dotBright: {
    backgroundColor: 'rgba(255, 252, 245, 0.96)',
    height: 3.2,
    shadowColor: '#ffffff',
    shadowOpacity: 0.34,
    shadowRadius: 7,
    width: 3.2,
  },
  dotAqua: {
    backgroundColor: 'rgba(89, 242, 211, 0.9)',
    height: 3.2,
    shadowColor: '#59f2d3',
    shadowOpacity: 0.42,
    shadowRadius: 10,
    width: 3.2,
  },
  dotBlue: {
    backgroundColor: 'rgba(118, 154, 255, 0.9)',
    height: 3.2,
    shadowColor: '#769aff',
    shadowOpacity: 0.42,
    shadowRadius: 10,
    width: 3.2,
  },
  dotCyan: {
    backgroundColor: 'rgba(79, 229, 255, 0.9)',
    height: 3.2,
    shadowColor: '#31dfff',
    shadowOpacity: 0.46,
    shadowRadius: 10,
    width: 3.2,
  },
  dotViolet: {
    backgroundColor: 'rgba(174, 146, 255, 0.84)',
    height: 3.2,
    shadowColor: '#ae92ff',
    shadowOpacity: 0.36,
    shadowRadius: 9,
    width: 3.2,
  },
  halftoneVignette: {
    backgroundColor: 'rgba(0, 0, 0, 0.28)',
    bottom: 0,
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  keyboard: {
    flex: 1,
    zIndex: 1,
  },
  scrollContent: {
    backgroundColor: 'transparent',
    flexGrow: 1,
    paddingHorizontal: 18,
    paddingVertical: 22,
  },
  loginScrollContent: {
    backgroundColor: 'transparent',
    flexGrow: 1,
    justifyContent: 'center',
    padding: 18,
  },
  shell: {
    alignSelf: 'center',
    maxWidth: 1040,
    minHeight: '100%',
    width: '100%',
  },
  dashboardShell: {
    maxWidth: 620,
  },
  loginShell: {
    alignSelf: 'center',
    maxWidth: 520,
    width: '100%',
  },
  appWindow: {
    backgroundColor: 'rgba(20, 22, 29, 0.72)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    overflow: 'hidden',
    shadowColor: '#000000',
    shadowOffset: { height: 24, width: 0 },
    shadowOpacity: 0.34,
    shadowRadius: 42,
    elevation: 14,
  },
  appToolbar: {
    alignItems: 'center',
    backgroundColor: 'rgba(26, 28, 35, 0.82)',
    borderBottomColor: 'rgba(255, 255, 255, 0.1)',
    borderBottomWidth: 1,
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    minHeight: 56,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  appToolbarCopy: {
    flex: 1,
    gap: 2,
  },
  appToolbarLabel: {
    color: '#8fb7ff',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  appToolbarTitle: {
    color: '#f7f8fb',
    fontSize: 18,
    fontWeight: '900',
    letterSpacing: 0,
  },
  loginWindow: {
    backgroundColor: 'rgba(20, 22, 29, 0.72)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    overflow: 'hidden',
    shadowColor: '#000000',
    shadowOffset: { height: 24, width: 0 },
    shadowOpacity: 0.36,
    shadowRadius: 42,
    elevation: 14,
  },
  windowToolbar: {
    alignItems: 'center',
    backgroundColor: 'rgba(26, 28, 35, 0.78)',
    borderBottomColor: 'rgba(255, 255, 255, 0.1)',
    borderBottomWidth: 1,
    flexDirection: 'row',
    gap: 10,
    minHeight: 46,
    paddingHorizontal: 14,
  },
  windowControls: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 7,
    width: 74,
  },
  windowDot: {
    borderRadius: 8,
    height: 11,
    width: 11,
  },
  windowDotClose: {
    backgroundColor: '#ff5f57',
  },
  windowDotMinimize: {
    backgroundColor: '#ffbd2e',
  },
  windowDotMaximize: {
    backgroundColor: '#28c840',
  },
  windowTitle: {
    color: '#d9dde8',
    flex: 1,
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    textAlign: 'center',
  },
  windowToolbarSpacer: {
    width: 74,
  },
  loginHero: {
    backgroundColor: 'rgba(255, 255, 255, 0.045)',
    borderBottomColor: 'rgba(255, 255, 255, 0.08)',
    borderBottomWidth: 1,
    minHeight: 190,
    paddingBottom: 24,
    paddingHorizontal: 20,
    paddingTop: 34,
  },
  logoutButton: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  logoutText: {
    color: '#d9dde8',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  eyebrow: {
    color: '#8fb7ff',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  title: {
    color: '#f7f8fb',
    fontSize: 32,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 38,
    marginTop: 8,
    maxWidth: 720,
    textShadowColor: 'rgba(0, 0, 0, 0.38)',
    textShadowOffset: { height: 1, width: 0 },
    textShadowRadius: 8,
  },
  subtitle: {
    color: '#b8bfcc',
    fontSize: 16,
    lineHeight: 23,
    marginTop: 10,
    maxWidth: 680,
  },
  pageTitleRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  pageBackButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    flexShrink: 0,
    paddingHorizontal: 11,
    paddingVertical: 8,
  },
  pageBackText: {
    color: '#d9dde8',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  addMachineButton: {
    alignItems: 'center',
    backgroundColor: '#8fb7ff',
    borderColor: '#c7d8ff',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 48,
    paddingHorizontal: 14,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 8, width: 0 },
    shadowOpacity: 0.2,
    shadowRadius: 14,
  },
  stepper: {
    backgroundColor: 'rgba(17, 19, 25, 0.72)',
    borderBottomColor: 'rgba(255, 255, 255, 0.1)',
    borderBottomWidth: 1,
    borderTopColor: 'rgba(255, 255, 255, 0.08)',
    borderTopWidth: 1,
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 14,
  },
  stepItem: {
    alignItems: 'center',
    flex: 1,
    gap: 7,
  },
  stepDot: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    borderColor: 'rgba(255, 255, 255, 0.18)',
    borderRadius: 8,
    borderWidth: 1,
    height: 32,
    justifyContent: 'center',
    position: 'relative',
    width: 32,
  },
  stepPulse: {
    backgroundColor: 'rgba(143, 183, 255, 0.14)',
    borderColor: 'rgba(143, 183, 255, 0.36)',
    borderRadius: 8,
    borderWidth: 1,
    height: 32,
    position: 'absolute',
    width: 32,
  },
  stepDotActive: {
    backgroundColor: '#8fb7ff',
    borderColor: '#c7d8ff',
  },
  stepDotDone: {
    backgroundColor: 'rgba(143, 183, 255, 0.12)',
    borderColor: 'rgba(143, 183, 255, 0.4)',
  },
  stepNumber: {
    color: '#f0ead9',
    fontSize: 13,
    fontWeight: '900',
    letterSpacing: 0,
  },
  stepNumberActive: {
    color: '#111111',
  },
  stepNumberDone: {
    color: '#8fb7ff',
  },
  stepText: {
    color: '#9d9a91',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textAlign: 'center',
  },
  stepTextActive: {
    color: '#f7f8fb',
  },
  stepCard: {
    margin: 0,
    padding: 16,
  },
  dashboardStepCard: {
    padding: 18,
  },
  loginPanel: {
    backgroundColor: 'rgba(22, 24, 31, 0.62)',
    borderBottomLeftRadius: 8,
    borderBottomRightRadius: 8,
    borderColor: 'rgba(255, 255, 255, 0.13)',
    borderTopWidth: 0,
    borderWidth: 1,
    overflow: 'hidden',
    padding: 16,
    shadowColor: '#000000',
    shadowOffset: { height: 18, width: 0 },
    shadowOpacity: 0.22,
    shadowRadius: 28,
    elevation: 12,
  },
  sectionTitle: {
    color: '#f7f8fb',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
    marginBottom: 8,
    textShadowColor: 'rgba(143, 183, 255, 0.18)',
    textShadowOffset: { height: 0, width: 0 },
    textShadowRadius: 10,
    textTransform: 'uppercase',
  },
  pageTitle: {
    flexShrink: 1,
    marginBottom: 0,
  },
  helperText: {
    color: '#b8bfcc',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
  },
  dashboardScreen: {
    gap: 16,
  },
  dashboardHeroCard: {
    alignItems: 'flex-start',
    backgroundColor: 'rgba(255, 255, 255, 0.028)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    borderLeftColor: 'rgba(143, 183, 255, 0.34)',
    borderLeftWidth: 3,
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    minHeight: 84,
    padding: 16,
  },
  dashboardHeroCopy: {
    flex: 1,
    gap: 4,
    paddingRight: 6,
  },
  dashboardHeroLabel: {
    color: '#8fb7ff',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  dashboardHeroTitle: {
    color: '#f7f8fb',
    fontSize: 20,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 24,
  },
  dashboardHeroMeta: {
    color: '#aeb6c6',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 18,
  },
  dashboardHeroBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#b8bfcc',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    overflow: 'hidden',
    paddingHorizontal: 8,
    paddingVertical: 6,
    textTransform: 'uppercase',
  },
  dashboardHeroBadgeActive: {
    backgroundColor: 'rgba(143, 183, 255, 0.12)',
    borderColor: 'rgba(143, 183, 255, 0.34)',
    color: '#c7d8ff',
  },
  dashboardActionGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  dashboardActionTile: {
    backgroundColor: 'rgba(15, 18, 26, 0.9)',
    borderColor: 'rgba(143, 183, 255, 0.24)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
    minHeight: 132,
    padding: 14,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 10, width: 0 },
    shadowOpacity: 0.12,
    shadowRadius: 18,
    elevation: 6,
    width: '48.5%',
  },
  dashboardActionTileFullWidth: {
    width: '100%',
  },
  dashboardActionTilePrimary: {
    backgroundColor: 'rgba(143, 183, 255, 0.16)',
    borderColor: 'rgba(143, 183, 255, 0.42)',
  },
  dashboardActionTileWarning: {
    backgroundColor: 'rgba(255, 214, 102, 0.1)',
    borderColor: 'rgba(255, 214, 102, 0.24)',
  },
  dashboardActionTileDisabled: {
    opacity: 0.72,
  },
  dashboardActionLabel: {
    color: '#8fb7ff',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  dashboardActionLabelPrimary: {
    color: '#d9e6ff',
  },
  dashboardActionValue: {
    color: '#f7f8fb',
    fontSize: 22,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 26,
  },
  dashboardActionValuePrimary: {
    color: '#ffffff',
  },
  dashboardActionValueDisabled: {
    color: '#d7dce6',
  },
  dashboardActionMeta: {
    color: '#aeb6c6',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 18,
  },
  dashboardActionMetaPrimary: {
    color: '#edf3ff',
  },
  dashboardActionMetaDisabled: {
    color: '#b2b9c6',
  },
  dashboardActionFooter: {
    alignItems: 'flex-start',
    marginTop: 'auto',
    paddingTop: 4,
  },
  pressableCue: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(143, 183, 255, 0.12)',
    borderColor: 'rgba(143, 183, 255, 0.34)',
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 6,
    paddingHorizontal: 9,
    paddingVertical: 5,
  },
  pressableCueText: {
    color: '#d7e4ff',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  pressableCueArrow: {
    color: '#d7e4ff',
    fontSize: 14,
    fontWeight: '900',
    lineHeight: 14,
  },
  dashboardSection: {
    gap: 10,
  },
  dashboardSectionHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  dashboardSectionTitle: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '900',
    letterSpacing: 0,
  },
  dashboardInlineLink: {
    alignItems: 'center',
    minHeight: 32,
    justifyContent: 'center',
    paddingHorizontal: 4,
  },
  dashboardInlineLinkText: {
    color: '#8fb7ff',
    fontSize: 13,
    fontWeight: '900',
    letterSpacing: 0,
  },
  dashboardList: {
    gap: 10,
  },
  dashboardHistoryRow: {
    backgroundColor: 'rgba(15, 18, 26, 0.88)',
    borderColor: 'rgba(143, 183, 255, 0.18)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 6,
    minHeight: 78,
    paddingHorizontal: 12,
    paddingVertical: 12,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 8, width: 0 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 4,
  },
  dashboardHistoryRowTop: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'space-between',
  },
  dashboardHistoryRowMachine: {
    color: '#f7f8fb',
    flex: 1,
    fontSize: 15,
    fontWeight: '800',
    letterSpacing: 0,
  },
  historyCodeChipRow: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  historyCodeChipRowLarge: {
    marginTop: 5,
  },
  historyCodeChip: {
    backgroundColor: 'rgba(143, 183, 255, 0.1)',
    borderColor: 'rgba(143, 183, 255, 0.3)',
    borderRadius: 6,
    borderWidth: 1,
    maxWidth: '100%',
    minHeight: 25,
    paddingHorizontal: 8,
    paddingVertical: 3,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 2, width: 0 },
    shadowOpacity: 0.1,
    shadowRadius: 5,
    elevation: 1,
  },
  historyCodeChipLarge: {
    minHeight: 29,
    paddingHorizontal: 9,
    paddingVertical: 4,
  },
  historyCodeChipText: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 18,
  },
  historyCodeChipTextLarge: {
    fontSize: 17,
    lineHeight: 20,
  },
  dashboardHistoryRowMeta: {
    color: '#99a3b5',
    flex: 1,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0,
  },
  dashboardHistoryRowFooter: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    marginTop: 2,
  },
  dashboardStateBox: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
    paddingHorizontal: 14,
    paddingVertical: 16,
  },
  dashboardStateText: {
    color: '#b8bfcc',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
    textAlign: 'center',
  },
  contentScreen: {
    gap: 16,
  },
  machinesScreen: {
    gap: 16,
  },
  machinesHeroCard: {
    alignItems: 'flex-start',
    backgroundColor: 'rgba(255, 255, 255, 0.028)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    borderLeftColor: 'rgba(143, 183, 255, 0.34)',
    borderLeftWidth: 3,
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    minHeight: 84,
    padding: 16,
  },
  machinesHeroCopy: {
    flex: 1,
    gap: 4,
    paddingRight: 6,
  },
  machinesHeroTitle: {
    color: '#f7f8fb',
    fontSize: 20,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 24,
  },
  machinesHeroMeta: {
    color: '#aeb6c6',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 18,
  },
  machinesSectionBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#c4cad6',
    fontSize: 11,
    fontWeight: '900',
    letterSpacing: 0,
    overflow: 'hidden',
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  subsectionTitle: {
    color: '#d9dde8',
    fontSize: 13,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 16,
    textTransform: 'uppercase',
  },
  savedMachineList: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  savedMachinePill: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  savedMachinePillActive: {
    backgroundColor: 'rgba(143, 183, 255, 0.13)',
    borderColor: 'rgba(143, 183, 255, 0.42)',
  },
  savedMachineText: {
    color: '#b8bfcc',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0,
  },
  savedMachineTextActive: {
    color: '#dbe6ff',
  },
  scanHistoryList: {
    gap: 10,
  },
  scanHistoryRow: {
    backgroundColor: 'rgba(15, 18, 26, 0.88)',
    borderColor: 'rgba(143, 183, 255, 0.18)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 8, width: 0 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 4,
  },
  scanHistoryRowHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  scanHistoryCopy: {
    flex: 1,
    gap: 3,
  },
  scanHistoryMachine: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '800',
    letterSpacing: 0,
  },
  scanHistoryMeta: {
    color: '#9ca5b6',
    flex: 1,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0,
  },
  scanHistoryCodes: {
    color: '#f7f8fb',
    fontSize: 18,
    fontWeight: '900',
    letterSpacing: 0,
  },
  scanHistoryMatch: {
    color: '#8fb7ff',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 18,
  },
  scanHistoryRowFooter: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
    marginTop: 2,
  },
  scanOverviewStack: {
    gap: 16,
  },
  scanOverviewMetaCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.028)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 12,
    padding: 16,
  },
  scanOverviewMetaRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  scanOverviewMetaBlock: {
    flex: 1,
    minWidth: 180,
  },
  scanOverviewMetaValue: {
    color: '#f1f4fb',
    fontSize: 15,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 21,
    marginTop: 4,
  },
  scanOverviewCodeValue: {
    color: '#f7f8fb',
    fontSize: 20,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 4,
  },
  scanOverviewMatchedBox: {
    backgroundColor: 'rgba(143, 183, 255, 0.08)',
    borderColor: 'rgba(143, 183, 255, 0.18)',
    borderRadius: 8,
    borderWidth: 1,
    padding: 12,
  },
  scanOverviewMatchedText: {
    color: '#d7e4ff',
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 20,
    marginTop: 4,
  },
  machineList: {
    gap: 10,
  },
  machineRow: {
    backgroundColor: 'rgba(255, 255, 255, 0.03)',
    borderColor: 'rgba(255, 255, 255, 0.09)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 12,
    minHeight: 92,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  machineRowSelected: {
    backgroundColor: 'rgba(143, 183, 255, 0.14)',
    borderColor: 'rgba(143, 183, 255, 0.82)',
    borderWidth: 2,
  },
  machineRowTop: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  machineInfo: {
    flex: 1,
    gap: 3,
    paddingRight: 8,
  },
  machineName: {
    color: '#f7f8fb',
    fontSize: 17,
    fontWeight: '800',
    letterSpacing: 0,
  },
  machineMeta: {
    color: '#aeb6c6',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 18,
  },
  machineStateBadge: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#c4cad6',
    fontSize: 10,
    fontWeight: '900',
    letterSpacing: 0,
    overflow: 'hidden',
    paddingHorizontal: 8,
    paddingVertical: 5,
    textTransform: 'uppercase',
  },
  machineStateBadgeActive: {
    backgroundColor: 'rgba(143, 183, 255, 0.12)',
    borderColor: 'rgba(143, 183, 255, 0.32)',
    color: '#d1e0ff',
  },
  machineRowActions: {
    flexDirection: 'row',
    gap: 10,
  },
  machineActionButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 44,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  machineActionButtonActive: {
    backgroundColor: 'rgba(143, 183, 255, 0.13)',
    borderColor: 'rgba(143, 183, 255, 0.42)',
  },
  machineActionButtonDanger: {
    borderColor: 'rgba(255, 107, 61, 0.3)',
  },
  machineActionButtonText: {
    color: '#d9dde8',
    fontSize: 13,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  machineActionButtonTextActive: {
    color: '#c7d8ff',
  },
  selectLabel: {
    color: '#8fb7ff',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0,
  },
  selectLabelSelected: {
    color: '#c7d8ff',
  },
  previewImage: {
    backgroundColor: 'rgba(255, 255, 255, 0.04)',
    borderRadius: 8,
    height: 230,
    marginTop: 16,
    width: '100%',
  },
  previewEmpty: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    height: 190,
    justifyContent: 'center',
    marginTop: 16,
  },
  previewText: {
    color: '#8d8a82',
    fontSize: 15,
    fontWeight: '800',
    letterSpacing: 0,
  },
  screenshotCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.028)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 16,
    overflow: 'hidden',
    padding: 12,
  },
  screenshotCardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  screenshotCardTitleWrap: {
    flex: 1,
    gap: 4,
  },
  screenshotCardText: {
    color: '#d9d4c6',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
  },
  screenshotPreviewFrame: {
    backgroundColor: 'rgba(7, 8, 14, 0.82)',
    borderColor: 'rgba(143, 183, 255, 0.22)',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 12,
    overflow: 'hidden',
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 10, width: 0 },
    shadowOpacity: 0.12,
    shadowRadius: 18,
    elevation: 6,
  },
  screenshotPreviewImage: {
    backgroundColor: 'rgba(255, 255, 255, 0.02)',
    overflow: 'hidden',
    width: '100%',
  },
  screenshotPreviewNativeImage: {
    height: '100%',
    width: '100%',
  },
  screenshotPreviewFooter: {
    alignItems: 'flex-end',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderTopColor: 'rgba(255, 255, 255, 0.08)',
    borderTopWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 14,
  },
  navRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  button: {
    alignItems: 'center',
    backgroundColor: '#8fb7ff',
    borderColor: '#c7d8ff',
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 50,
    paddingHorizontal: 14,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 8, width: 0 },
    shadowOpacity: 0.22,
    shadowRadius: 14,
    elevation: 6,
  },
  secondaryButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 50,
    paddingHorizontal: 14,
  },
  fullButton: {
    alignItems: 'center',
    backgroundColor: '#8fb7ff',
    borderColor: '#c7d8ff',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 50,
    paddingHorizontal: 14,
    shadowColor: '#8fb7ff',
    shadowOffset: { height: 8, width: 0 },
    shadowOpacity: 0.22,
    shadowRadius: 14,
    elevation: 6,
  },
  authSwitchButton: {
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 44,
    marginTop: 10,
  },
  authSwitchText: {
    color: '#8fb7ff',
    fontSize: 14,
    fontWeight: '900',
    letterSpacing: 0,
  },
  buttonDisabled: {
    backgroundColor: 'rgba(255, 255, 255, 0.08)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    shadowOpacity: 0,
  },
  buttonPressed: {
    opacity: 0.86,
  },
  pressed: {
    opacity: 0.9,
  },
  buttonText: {
    color: '#10131a',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
  },
  secondaryButtonText: {
    color: '#d9dde8',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
  },
  inlineState: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    marginTop: 14,
  },
  inlineStateText: {
    color: '#f7f8fb',
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 0,
  },
  qualityBox: {
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 14,
    padding: 12,
  },
  qualityPass: {
    backgroundColor: 'rgba(27, 85, 42, 0.2)',
    borderColor: 'rgba(104, 216, 111, 0.55)',
  },
  qualityWarn: {
    backgroundColor: 'rgba(143, 183, 255, 0.1)',
    borderColor: 'rgba(143, 183, 255, 0.48)',
  },
  qualityFail: {
    backgroundColor: 'rgba(255, 107, 61, 0.12)',
    borderColor: 'rgba(255, 107, 61, 0.62)',
  },
  qualityTitle: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '900',
    letterSpacing: 0,
  },
  qualityText: {
    color: '#d9d4c6',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
    marginTop: 5,
  },
  qualityMeta: {
    color: '#8d8a82',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    marginTop: 8,
  },
  detectedBox: {
    backgroundColor: 'rgba(255, 255, 255, 0.045)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 16,
    padding: 12,
  },
  detectedLabel: {
    color: '#a9a49a',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  detectedText: {
    color: '#8fb7ff',
    fontSize: 22,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 5,
  },
  detectedSubText: {
    color: '#f7f8fb',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0,
    marginTop: 4,
  },
  confirmNudgeBox: {
    backgroundColor: 'rgba(143, 183, 255, 0.09)',
    borderColor: 'rgba(143, 183, 255, 0.28)',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 16,
    padding: 12,
  },
  confirmNudgeTitle: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '900',
    letterSpacing: 0,
  },
  confirmNudgeText: {
    color: '#c9d2e3',
    fontSize: 14,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 20,
    marginTop: 5,
  },
  inputGrid: {
    gap: 12,
    marginTop: 14,
  },
  inputGroup: {
    width: '100%',
  },
  inputLabel: {
    color: '#a9a49a',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    marginBottom: 6,
    textTransform: 'uppercase',
  },
  input: {
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.12)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#f7f8fb',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0,
    minHeight: 48,
    paddingHorizontal: 12,
  },
  inputTall: {
    minHeight: 76,
    paddingTop: 12,
    textAlignVertical: 'top',
  },
  confirmInputGrid: {
    gap: 10,
    marginTop: 12,
    position: 'relative',
    zIndex: 2,
  },
  confirmRowsHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  smallActionButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(143, 183, 255, 0.12)',
    borderColor: 'rgba(143, 183, 255, 0.32)',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 34,
    paddingHorizontal: 10,
  },
  smallActionButtonText: {
    color: '#c7d8ff',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  confirmRows: {
    gap: 10,
    position: 'relative',
    zIndex: 2,
  },
  confirmRow: {
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.09)',
    borderRadius: 8,
    borderWidth: 1,
    gap: 10,
    padding: 10,
  },
  confirmRowTop: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
  },
  confirmCodeInput: {
    flex: 1,
  },
  removeRowButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    height: 48,
    justifyContent: 'center',
    width: 48,
  },
  removeRowButtonText: {
    color: '#f7f8fb',
    fontSize: 18,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 20,
  },
  colorChoiceRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  colorChoice: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.045)',
    borderColor: 'rgba(255, 255, 255, 0.12)',
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 7,
    minHeight: 36,
    paddingHorizontal: 9,
  },
  colorChoiceSelected: {
    backgroundColor: 'rgba(143, 183, 255, 0.13)',
    borderColor: 'rgba(143, 183, 255, 0.5)',
  },
  colorChoiceSwatch: {
    borderColor: 'rgba(255, 255, 255, 0.42)',
    borderRadius: 999,
    borderWidth: 1,
    height: 14,
    width: 14,
  },
  colorChoiceSwatchUnknown: {
    backgroundColor: 'rgba(255, 255, 255, 0.16)',
  },
  colorChoiceText: {
    color: '#c7cdd8',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
  },
  colorChoiceTextSelected: {
    color: '#eef4ff',
  },
  confirmNavRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 6,
    position: 'relative',
    zIndex: 1,
  },
  resultPanel: {
    backgroundColor: 'rgba(255, 255, 255, 0.028)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 12,
    overflow: 'hidden',
  },
  resultHeader: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    borderBottomColor: 'rgba(255, 255, 255, 0.1)',
    borderBottomWidth: 1,
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    minHeight: 70,
    paddingHorizontal: 16,
  },
  kicker: {
    color: '#a9a49a',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  resultTitle: {
    color: '#f7f8fb',
    fontSize: 19,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 3,
  },
  badge: {
    backgroundColor: 'rgba(143, 183, 255, 0.11)',
    borderColor: 'rgba(143, 183, 255, 0.55)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#c7d8ff',
    fontSize: 13,
    fontWeight: '900',
    letterSpacing: 0,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  resultBody: {
    padding: 16,
  },
  matchList: {
    gap: 12,
    marginTop: 14,
  },
  matchCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.024)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderLeftWidth: 5,
    borderWidth: 1,
    overflow: 'hidden',
    padding: 12,
    position: 'relative',
  },
  matchCardColorWash: {
    bottom: 0,
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  matchCardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  matchCardActions: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
  },
  matchCode: {
    color: '#8fb7ff',
    fontSize: 22,
    fontWeight: '900',
    letterSpacing: 0,
  },
  matchModule: {
    color: '#aaa59a',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0,
    marginTop: 2,
  },
  matchScore: {
    backgroundColor: 'rgba(143, 183, 255, 0.1)',
    borderColor: 'rgba(143, 183, 255, 0.28)',
    borderRadius: 8,
    borderWidth: 1,
    color: '#f7f8fb',
    fontSize: 14,
    fontWeight: '900',
    letterSpacing: 0,
    paddingHorizontal: 9,
    paddingVertical: 5,
  },
  documentationButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  documentationButtonIcon: {
    alignItems: 'center',
    backgroundColor: 'rgba(143, 183, 255, 0.18)',
    borderColor: 'rgba(143, 183, 255, 0.42)',
    borderRadius: 999,
    borderWidth: 1,
    height: 18,
    justifyContent: 'center',
    width: 18,
  },
  documentationButtonIconText: {
    color: '#cfe0ff',
    fontSize: 12,
    fontWeight: '900',
    lineHeight: 12,
  },
  documentationButtonText: {
    color: '#dce5f9',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  matchTitle: {
    color: '#f7f8fb',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 22,
    marginTop: 10,
  },
  colorMeaningPanel: {
    alignItems: 'flex-start',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.09)',
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
    padding: 10,
  },
  colorMeaningSwatch: {
    borderColor: 'rgba(255, 255, 255, 0.42)',
    borderRadius: 999,
    borderWidth: 1,
    height: 18,
    marginTop: 1,
    width: 18,
  },
  colorMeaningCopy: {
    flex: 1,
    gap: 3,
  },
  colorMeaningLabel: {
    color: '#f7f8fb',
    fontSize: 14,
    fontWeight: '900',
    letterSpacing: 0,
  },
  colorMeaningDescription: {
    color: '#c9d2e3',
    fontSize: 13,
    fontWeight: '700',
    letterSpacing: 0,
    lineHeight: 19,
  },
  textBlock: {
    marginTop: 14,
  },
  textLabel: {
    color: '#8fb7ff',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    marginBottom: 5,
    textTransform: 'uppercase',
  },
  bodyText: {
    color: '#d9d4c6',
    fontSize: 15,
    letterSpacing: 0,
    lineHeight: 22,
  },
  stateBox: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 132,
    padding: 18,
  },
  bootScreen: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    padding: 20,
  },
  modalBackdrop: {
    alignItems: 'center',
    backgroundColor: 'rgba(0, 0, 0, 0.58)',
    flex: 1,
    justifyContent: 'center',
    padding: 18,
  },
  imageViewerBackdrop: {
    backgroundColor: 'rgba(2, 3, 7, 0.92)',
    flex: 1,
    padding: 16,
  },
  imageViewerPanel: {
    alignSelf: 'center',
    flex: 1,
    gap: 14,
    maxWidth: 1200,
    width: '100%',
  },
  imageViewerHeader: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  imageViewerTitleWrap: {
    flex: 1,
  },
  imageViewerToolbar: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  viewerToolButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  viewerToolButtonText: {
    color: '#f7f8fb',
    fontSize: 24,
    fontWeight: '700',
    lineHeight: 24,
  },
  viewerActionButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.055)',
    borderColor: 'rgba(255, 255, 255, 0.16)',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 40,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  viewerActionButtonText: {
    color: '#d9dde8',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  imageViewerZoomText: {
    color: '#f7f8fb',
    fontSize: 15,
    fontWeight: '900',
    letterSpacing: 0,
    minWidth: 58,
    textAlign: 'center',
  },
  imageViewerViewport: {
    backgroundColor: 'rgba(7, 8, 14, 0.86)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    overflow: 'hidden',
  },
  imageViewerHorizontalContent: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageViewerVerticalContent: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  imageViewerImageFrame: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageViewerImage: {
    height: '100%',
    width: '100%',
  },
  documentationModalPanel: {
    maxWidth: 860,
  },
  documentationModalTitleWrap: {
    flex: 1,
    paddingRight: 12,
  },
  documentationModalContent: {
    gap: 12,
    paddingBottom: 4,
    paddingTop: 16,
  },
  documentationCard: {
    backgroundColor: 'rgba(255, 255, 255, 0.024)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    padding: 14,
  },
  documentationCardTitle: {
    color: '#f7f8fb',
    fontSize: 17,
    fontWeight: '900',
    letterSpacing: 0,
    marginBottom: 12,
  },
  documentationContent: {
    gap: 10,
  },
  documentationHeading: {
    color: '#f7f8fb',
    fontWeight: '900',
    letterSpacing: 0,
  },
  documentationHeadingLarge: {
    fontSize: 19,
    lineHeight: 26,
  },
  documentationHeadingSmall: {
    fontSize: 16,
    lineHeight: 23,
  },
  documentationHeadingText: {
    color: '#f7f8fb',
  },
  documentationParagraph: {
    color: '#d9d4c6',
    fontSize: 15,
    letterSpacing: 0,
    lineHeight: 22,
  },
  documentationParagraphMedia: {
    gap: 10,
  },
  documentationParagraphText: {
    color: '#d9d4c6',
  },
  documentationBoldText: {
    fontWeight: '900',
  },
  documentationItalicText: {
    fontStyle: 'italic',
  },
  documentationUnderlineText: {
    textDecorationLine: 'underline',
  },
  documentationStrikeText: {
    textDecorationLine: 'line-through',
  },
  documentationInlineCodeText: {
    backgroundColor: 'rgba(255, 255, 255, 0.06)',
    color: '#f7f8fb',
    fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
    fontSize: 14,
  },
  documentationLinkText: {
    color: '#8fb7ff',
    textDecorationLine: 'underline',
  },
  documentationList: {
    gap: 8,
  },
  documentationListItemRow: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 10,
  },
  documentationBullet: {
    color: '#8fb7ff',
    fontSize: 15,
    fontWeight: '900',
    lineHeight: 22,
    minWidth: 18,
  },
  documentationListItemBody: {
    flex: 1,
    gap: 6,
  },
  documentationQuote: {
    backgroundColor: 'rgba(255, 255, 255, 0.03)',
    borderLeftColor: '#8fb7ff',
    borderLeftWidth: 3,
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  documentationCodeBlock: {
    backgroundColor: 'rgba(0, 0, 0, 0.36)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    padding: 12,
  },
  documentationCodeBlockText: {
    color: '#f7f8fb',
    fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
    fontSize: 14,
    lineHeight: 20,
  },
  documentationImage: {
    alignSelf: 'stretch',
    backgroundColor: 'rgba(255, 255, 255, 0.03)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    height: 220,
    width: '100%',
  },
  documentationInlineImage: {
    alignSelf: 'stretch',
    backgroundColor: 'rgba(255, 255, 255, 0.03)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    height: 220,
    width: '100%',
  },
  documentationVideoBlock: {
    backgroundColor: 'rgba(255, 255, 255, 0.03)',
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRadius: 8,
    borderWidth: 1,
    overflow: 'hidden',
  },
  documentationVideoFrame: {
    aspectRatio: 16 / 9,
    borderWidth: 0,
    width: '100%',
  },
  documentationVideoTitle: {
    color: '#f7f8fb',
    fontSize: 14,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 20,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  documentationVideoLinkCard: {
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(143, 183, 255, 0.22)',
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 12,
    minHeight: 86,
    padding: 12,
  },
  documentationVideoThumb: {
    alignItems: 'center',
    backgroundColor: 'rgba(143, 183, 255, 0.14)',
    borderColor: 'rgba(143, 183, 255, 0.34)',
    borderRadius: 8,
    borderWidth: 1,
    height: 56,
    justifyContent: 'center',
    width: 78,
  },
  documentationVideoPlay: {
    color: '#d7e4ff',
    fontSize: 20,
    fontWeight: '900',
    letterSpacing: 0,
  },
  documentationVideoCopy: {
    flex: 1,
  },
  documentationVideoMeta: {
    color: '#8fb7ff',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    paddingHorizontal: 12,
    textTransform: 'uppercase',
  },
  documentationTable: {
    borderColor: 'rgba(255, 255, 255, 0.12)',
    borderRadius: 8,
    borderWidth: 1,
    overflow: 'hidden',
  },
  documentationTableRow: {
    flexDirection: 'row',
  },
  documentationTableCell: {
    borderColor: 'rgba(255, 255, 255, 0.08)',
    borderRightWidth: 1,
    borderTopWidth: 1,
    flex: 1,
    gap: 6,
    minWidth: 0,
    padding: 10,
  },
  documentationTableHeaderCell: {
    backgroundColor: 'rgba(143, 183, 255, 0.08)',
  },
  documentationDivider: {
    backgroundColor: 'rgba(255, 255, 255, 0.12)',
    height: 1,
  },
  documentationFallbackBlock: {
    gap: 8,
  },
  modalPanel: {
    backgroundColor: 'rgba(20, 22, 29, 0.96)',
    borderColor: 'rgba(255, 255, 255, 0.14)',
    borderRadius: 8,
    borderWidth: 1,
    overflow: 'hidden',
    maxHeight: '86%',
    maxWidth: 680,
    padding: 16,
    shadowColor: '#000000',
    shadowOffset: { height: 24, width: 0 },
    shadowOpacity: 0.36,
    shadowRadius: 42,
    width: '100%',
  },
  modalInputGroup: {
    flexGrow: 0,
    flexShrink: 0,
    marginTop: 16,
  },
  modalResultArea: {
    flexGrow: 0,
    flexShrink: 1,
    marginTop: 16,
  },
  modalResultViewport: {
    flexGrow: 0,
    flexShrink: 1,
    maxHeight: 360,
  },
  modalResultStack: {
    position: 'relative',
  },
  modalResultViewportLoading: {
    opacity: 0.34,
  },
  modalResultList: {
    gap: 10,
    paddingBottom: 4,
  },
  modalLoadingOverlay: {
    alignItems: 'center',
    backgroundColor: 'rgba(8, 10, 16, 0.18)',
    borderRadius: 8,
    bottom: 0,
    justifyContent: 'center',
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
  },
  modalMessageBox: {
    alignItems: 'flex-start',
    backgroundColor: 'rgba(255, 255, 255, 0.035)',
    borderColor: 'rgba(255, 255, 255, 0.1)',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 58,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  modalLoadingBox: {
    flexDirection: 'row',
    gap: 10,
  },
  modalMessageText: {
    color: '#bbb7ad',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
    textAlign: 'left',
  },
  stateText: {
    color: '#bbb7ad',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
    marginTop: 8,
    textAlign: 'center',
  },
  error: {
    color: '#ff6b3d',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
    marginTop: 12,
  },
});
