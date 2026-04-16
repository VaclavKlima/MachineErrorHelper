import { QueryClient, QueryClientProvider, useMutation, useQuery } from '@tanstack/react-query';
import * as ImagePicker from 'expo-image-picker';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Image as RNImage,
  ImageBackground,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { z } from 'zod';

const queryClient = new QueryClient();
const apiBaseUrl = process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8090/api';
const workshopImage = {
  uri: 'https://images.unsplash.com/photo-1581093458791-9d09eaf942e9?auto=format&fit=crop&w=1400&q=82',
};

const machineSchema = z.object({
  id: z.number(),
  name: z.string(),
  slug: z.string(),
  manufacturer: z.string().nullable(),
  model_number: z.string().nullable(),
});

const machinesResponseSchema = z.object({
  data: z.array(machineSchema),
});

const diagnosisCreateSchema = z.object({
  id: z.string(),
  status: z.string(),
  poll_url: z.string(),
});

const diagnosticEntrySchema = z
  .object({
    id: z.number(),
    module_key: z.string().nullable(),
    primary_code: z.string().nullable(),
    title: z.string().nullable(),
    meaning: z.string().nullable(),
    cause: z.string().nullable(),
    recommended_action: z.string().nullable(),
    severity: z.string().nullable(),
    source_page_number: z.number().nullable(),
    confidence: z.number().nullable(),
  })
  .passthrough();

const diagnosisCandidateSchema = z
  .object({
    id: z.number(),
    candidate_code: z.string().nullable(),
    normalized_code: z.string().nullable(),
    source: z.string().nullable(),
    confidence: z.number().nullable(),
    metadata: z.record(z.string(), z.unknown()).nullable().optional(),
    matched_diagnostic_entry: diagnosticEntrySchema.nullable().optional(),
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

type Machine = z.infer<typeof machineSchema>;
type DiagnosisCreate = z.infer<typeof diagnosisCreateSchema>;
type DiagnosisDetail = z.infer<typeof diagnosisDetailSchema>['data'];
type DiagnosisCandidate = z.infer<typeof diagnosisCandidateSchema>;
type DiagnosticEntry = z.infer<typeof diagnosticEntrySchema>;
type ImageSource = 'camera' | 'library';
type WizardStep = 'machine' | 'upload' | 'confirm' | 'result';
type ImageQualityStatus = 'pass' | 'warn' | 'fail';
type ImageQuality = {
  status: ImageQualityStatus;
  sharpness: number | null;
  messages: string[];
};

const steps: Array<{ id: WizardStep; title: string }> = [
  { id: 'machine', title: 'Machine' },
  { id: 'upload', title: 'Screenshot' },
  { id: 'confirm', title: 'Confirm codes' },
  { id: 'result', title: 'Result' },
];

async function parseJsonResponse<T>(response: Response, schema: z.ZodType<T>, fallbackMessage: string): Promise<T> {
  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    const message = typeof payload?.message === 'string' ? payload.message : fallbackMessage;

    throw new Error(message);
  }

  return schema.parse(payload);
}

async function fetchMachines(): Promise<Machine[]> {
  const response = await fetch(`${apiBaseUrl}/machines`);
  const payload = await parseJsonResponse(response, machinesResponseSchema, 'Could not load machines.');

  return payload.data;
}

async function fetchDiagnosis(id: string): Promise<DiagnosisDetail> {
  const response = await fetch(`${apiBaseUrl}/diagnoses/${id}`);
  const payload = await parseJsonResponse(response, diagnosisDetailSchema, 'Could not load diagnosis.');

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

async function uploadDiagnosis(input: { machineId: number; asset: ImagePicker.ImagePickerAsset }): Promise<DiagnosisCreate> {
  const formData = new FormData();

  formData.append('machine_id', String(input.machineId));
  await appendScreenshot(formData, input.asset);

  const response = await fetch(`${apiBaseUrl}/diagnoses`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
    },
    body: formData,
  });

  return parseJsonResponse(response, diagnosisCreateSchema, 'Could not upload screenshot.');
}

async function submitManualCode(input: { diagnosisId: string; codes: string[]; moduleKey: string }): Promise<DiagnosisDetail> {
  const response = await fetch(`${apiBaseUrl}/diagnoses/${input.diagnosisId}/manual-code`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      codes: input.codes,
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

function splitCodeInput(value: string): string[] {
  return Array.from(
    new Set(
      value
        .split(/[\s,;|]+/u)
        .map((code) => code.trim())
        .filter(Boolean),
    ),
  );
}

function formatPercent(value: number | null | undefined): string {
  return typeof value === 'number' ? `${Math.round(value * 100)}%` : 'n/a';
}

function statusLabel(status: string | undefined): string {
  switch (status) {
    case 'uploaded':
      return 'Queued';
    case 'processing':
      return 'Processing';
    case 'resolved':
      return 'Resolved';
    case 'needs_confirmation':
      return 'Needs check';
    case 'failed':
      return 'Failed';
    default:
      return 'Ready';
  }
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

function moduleFromDiagnosis(detail: DiagnosisDetail | null): string {
  const fromResult = resultString(detail, 'module_key');

  if (fromResult) {
    return fromResult;
  }

  const metadata = detail?.candidates?.[0]?.metadata ?? null;
  const fromMetadata = metadata?.module_key;

  return typeof fromMetadata === 'string' ? fromMetadata : 'PLUGSA';
}

function Stepper({ currentStep }: { currentStep: WizardStep }) {
  const currentIndex = steps.findIndex((step) => step.id === currentStep);

  return (
    <View style={styles.stepper}>
      {steps.map((step, index) => {
        const active = step.id === currentStep;
        const done = index < currentIndex;

        return (
          <View key={step.id} style={styles.stepItem}>
            <View style={[styles.stepDot, active && styles.stepDotActive, done && styles.stepDotDone]}>
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
  const message = typeof detail?.result?.message === 'string' ? detail.result.message : null;

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
          <Text style={styles.kicker}>Diagnosis</Text>
          <Text style={styles.resultTitle}>{statusLabel(detail.status)}</Text>
        </View>
        <Text style={styles.badge}>{detail.id.slice(-6)}</Text>
      </View>

      <View style={styles.resultBody}>
        {message ? <Text style={styles.helperText}>{message}</Text> : null}

        {entry ? (
          <ErrorPreviewCard candidate={null} entry={entry} />
        ) : candidates.length ? (
          <View style={styles.matchList}>
            {candidates.map((candidate) => (
              <ErrorPreviewCard key={candidate.id} candidate={candidate} entry={candidate.matched_diagnostic_entry ?? null} />
            ))}
          </View>
        ) : (
          <Text style={styles.helperText}>No visible code was confirmed yet.</Text>
        )}
      </View>
    </View>
  );
}

function ErrorPreviewCard({ candidate, entry }: { candidate: DiagnosisCandidate | null; entry: DiagnosticEntry | null }) {
  const code = entry?.primary_code ?? candidate?.candidate_code ?? candidate?.normalized_code ?? 'Code';
  const module = entry?.module_key ?? (typeof candidate?.metadata?.module_key === 'string' ? candidate.metadata.module_key : 'Not matched');

  return (
    <View style={styles.matchCard}>
      <View style={styles.matchCardHeader}>
        <View>
          <Text style={styles.matchCode}>{code}</Text>
          <Text style={styles.matchModule}>{module}</Text>
        </View>
        <Text style={styles.matchScore}>{formatPercent(candidate?.confidence ?? entry?.confidence)}</Text>
      </View>

      <Text style={styles.matchTitle}>{entry?.title || entry?.meaning || 'No approved manual meaning yet'}</Text>

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

function MachineErrorHelper() {
  const [step, setStep] = useState<WizardStep>('machine');
  const [selectedMachine, setSelectedMachine] = useState<Machine | null>(null);
  const [selectedImage, setSelectedImage] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [imageQuality, setImageQuality] = useState<ImageQuality | null>(null);
  const [imageValidationPending, setImageValidationPending] = useState(false);
  const [diagnosisId, setDiagnosisId] = useState<string | null>(null);
  const [autoAdvancedDiagnosisId, setAutoAdvancedDiagnosisId] = useState<string | null>(null);
  const [manualCode, setManualCode] = useState('');
  const [moduleKey, setModuleKey] = useState('PLUGSA');
  const [manualResult, setManualResult] = useState<DiagnosisDetail | null>(null);
  const fade = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    fade.setValue(0);
    Animated.timing(fade, {
      toValue: 1,
      duration: 220,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [fade, step]);

  const machines = useQuery({
    queryKey: ['machines'],
    queryFn: fetchMachines,
  });

  const diagnosis = useQuery({
    enabled: !!diagnosisId,
    queryKey: ['diagnosis', diagnosisId],
    queryFn: () => fetchDiagnosis(diagnosisId as string),
    refetchInterval: (query) => {
      const status = query.state.data?.status;

      return status === 'resolved' || status === 'needs_confirmation' || status === 'failed' ? false : 2500;
    },
  });

  const upload = useMutation({
    mutationFn: uploadDiagnosis,
    onSuccess: (payload) => {
      setDiagnosisId(payload.id);
      setAutoAdvancedDiagnosisId(null);
      setManualResult(null);
    },
  });

  const manualLookup = useMutation({
    mutationFn: submitManualCode,
    onSuccess: (payload) => {
      setManualResult(payload);
      queryClient.setQueryData(['diagnosis', payload.id], payload);
      setStep('result');
    },
  });

  const activeDiagnosis = manualResult ?? diagnosis.data ?? null;
  const manualCodes = splitCodeInput(manualCode);
  const canAnalyze = !!selectedMachine && !!selectedImage && imageQuality?.status !== 'fail' && !upload.isPending && !imageValidationPending;
  const canConfirm = !!diagnosisId && manualCodes.length > 0 && !manualLookup.isPending;
  const translateY = fade.interpolate({ inputRange: [0, 1], outputRange: [14, 0] });

  useEffect(() => {
    if (step !== 'upload' || !diagnosis.data || autoAdvancedDiagnosisId === diagnosis.data.id) {
      return;
    }

    const detail = diagnosis.data;

    if (detail.status === 'processing' || detail.status === 'uploaded') {
      return;
    }

    setModuleKey(moduleFromDiagnosis(detail));
    setManualCode(codesFromCandidates(detail.candidates ?? []));
    setAutoAdvancedDiagnosisId(detail.id);
    setStep('confirm');
  }, [autoAdvancedDiagnosisId, diagnosis.data, step]);

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
    setStep('machine');
    setSelectedMachine(null);
    setSelectedImage(null);
    setImageQuality(null);
    setDiagnosisId(null);
    setAutoAdvancedDiagnosisId(null);
    setManualResult(null);
    setManualCode('');
    setModuleKey('PLUGSA');
  }

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar style="light" />
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.keyboard}>
        <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled">
          <View style={styles.shell}>
            <ImageBackground source={workshopImage} resizeMode="cover" style={styles.hero} imageStyle={styles.heroImage}>
              <View style={styles.heroShade}>
                <Text style={styles.eyebrow}>Machine Error Helper</Text>
                <Text style={styles.title}>Find the alarm code before the line stops longer.</Text>
                <Text style={styles.subtitle}>Guided diagnosis for machine dashboards, from screenshot to repair hint.</Text>
              </View>
            </ImageBackground>

            <Stepper currentStep={step} />

            <Animated.View style={[styles.stepCard, { opacity: fade, transform: [{ translateY }] }]}>
              {step === 'machine' ? (
                <View>
                  <Text style={styles.sectionTitle}>Select machine</Text>
                  <Text style={styles.helperText}>Choose the machine that is showing the dashboard alarm.</Text>

                  {machines.isLoading ? (
                    <View style={styles.stateBox}>
                      <ActivityIndicator color="#ffd21e" />
                      <Text style={styles.stateText}>Loading configured machines</Text>
                    </View>
                  ) : machines.error ? (
                    <View style={styles.stateBox}>
                      <Text style={styles.error}>Backend is not reachable at {apiBaseUrl}</Text>
                    </View>
                  ) : (
                    <View style={styles.machineList}>
                      {(machines.data ?? []).map((machine) => {
                        const selected = selectedMachine?.id === machine.id;

                        return (
                          <Pressable
                            key={machine.id}
                            onPress={() => setSelectedMachine(machine)}
                            style={({ pressed }) => [
                              styles.machineRow,
                              selected && styles.machineRowSelected,
                              pressed && styles.pressed,
                            ]}
                          >
                            <View style={styles.machineInfo}>
                              <Text style={styles.machineName}>{machine.name}</Text>
                              <Text style={styles.machineMeta}>
                                {[machine.manufacturer, machine.model_number].filter(Boolean).join(' ') || 'No model details'}
                              </Text>
                            </View>
                            <Text style={[styles.selectLabel, selected && styles.selectLabelSelected]}>{selected ? 'Selected' : 'Choose'}</Text>
                          </Pressable>
                        );
                      })}
                    </View>
                  )}

                  <Pressable
                    disabled={!selectedMachine}
                    onPress={() => setStep('upload')}
                    style={({ pressed }) => [styles.fullButton, !selectedMachine && styles.buttonDisabled, pressed && styles.buttonPressed]}
                  >
                    <Text style={styles.buttonText}>Continue</Text>
                  </Pressable>
                </View>
              ) : null}

              {step === 'upload' ? (
                <View>
                  <Text style={styles.sectionTitle}>Upload screenshot</Text>
                  <Text style={styles.helperText}>The image is checked for resolution and blur before it is sent to Gemini.</Text>

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
                      <ActivityIndicator color="#ffd21e" />
                      <Text style={styles.inlineStateText}>Checking image quality</Text>
                    </View>
                  ) : null}

                  <QualityBadge quality={imageQuality} />

                  {upload.isPending || diagnosis.data?.status === 'processing' || diagnosis.data?.status === 'uploaded' ? (
                    <View style={styles.inlineState}>
                      <ActivityIndicator color="#ffd21e" />
                      <Text style={styles.inlineStateText}>Extracting visible codes</Text>
                    </View>
                  ) : null}

                  {upload.error ? <Text style={styles.error}>{upload.error.message}</Text> : null}
                  {diagnosis.error ? <Text style={styles.error}>{diagnosis.error.message}</Text> : null}

                  <View style={styles.navRow}>
                    <Pressable onPress={() => setStep('machine')} style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.secondaryButtonText}>Back</Text>
                    </Pressable>
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

              {step === 'confirm' ? (
                <View>
                  <Text style={styles.sectionTitle}>Confirm detected codes</Text>
                  <Text style={styles.helperText}>Correct the module or code list when the OCR result is wrong. This is kept separate from admin approval.</Text>

                  <View style={styles.detectedBox}>
                    <Text style={styles.detectedLabel}>AI detected</Text>
                    <Text style={styles.detectedText}>{resultString(activeDiagnosis, 'module_key') || moduleKey || 'Unknown module'}</Text>
                    <Text style={styles.detectedSubText}>{codesFromCandidates(activeDiagnosis?.candidates ?? []) || 'No code detected'}</Text>
                  </View>

                  <View style={styles.inputGrid}>
                    <View style={styles.inputGroup}>
                      <Text style={styles.inputLabel}>Module</Text>
                      <TextInput
                        autoCapitalize="characters"
                        onChangeText={setModuleKey}
                        placeholder="PLUGSA"
                        placeholderTextColor="#77736a"
                        style={styles.input}
                        value={moduleKey}
                      />
                    </View>

                    <View style={styles.inputGroup}>
                      <Text style={styles.inputLabel}>Codes</Text>
                      <TextInput
                        autoCapitalize="characters"
                        multiline
                        onChangeText={setManualCode}
                        placeholder="250 251 260"
                        placeholderTextColor="#77736a"
                        style={[styles.input, styles.inputTall]}
                        value={manualCode}
                      />
                    </View>
                  </View>

                  {manualLookup.error ? <Text style={styles.error}>{manualLookup.error.message}</Text> : null}

                  <View style={styles.navRow}>
                    <Pressable onPress={() => setStep('upload')} style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.secondaryButtonText}>Back</Text>
                    </Pressable>
                    <Pressable
                      disabled={!canConfirm}
                      onPress={() =>
                        diagnosisId &&
                        manualLookup.mutate({
                          diagnosisId,
                          codes: manualCodes,
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

              {step === 'result' ? (
                <View>
                  <Text style={styles.sectionTitle}>Error preview</Text>
                  <ResultPanel detail={activeDiagnosis} />

                  <View style={styles.navRow}>
                    <Pressable onPress={() => setStep('confirm')} style={({ pressed }) => [styles.secondaryButton, pressed && styles.buttonPressed]}>
                      <Text style={styles.secondaryButtonText}>Edit Codes</Text>
                    </Pressable>
                    <Pressable onPress={startOver} style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}>
                      <Text style={styles.buttonText}>New Diagnosis</Text>
                    </Pressable>
                  </View>
                </View>
              ) : null}
            </Animated.View>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <MachineErrorHelper />
    </QueryClientProvider>
  );
}

const styles = StyleSheet.create({
  screen: {
    backgroundColor: '#050505',
    flex: 1,
  },
  keyboard: {
    flex: 1,
  },
  scrollContent: {
    backgroundColor: '#050505',
    flexGrow: 1,
  },
  shell: {
    alignSelf: 'center',
    maxWidth: 1040,
    minHeight: '100%',
    width: '100%',
  },
  hero: {
    borderBottomColor: '#2b2b2b',
    borderBottomWidth: 1,
    minHeight: 205,
  },
  heroImage: {
    opacity: 0.54,
  },
  heroShade: {
    backgroundColor: 'rgba(5, 5, 5, 0.7)',
    flex: 1,
    justifyContent: 'flex-end',
    paddingBottom: 22,
    paddingHorizontal: 20,
    paddingTop: 42,
  },
  eyebrow: {
    color: '#ffd21e',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  title: {
    color: '#fff8d6',
    fontSize: 30,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 36,
    marginTop: 8,
    maxWidth: 720,
  },
  subtitle: {
    color: '#d5d0c2',
    fontSize: 16,
    lineHeight: 23,
    marginTop: 10,
    maxWidth: 680,
  },
  stepper: {
    backgroundColor: '#101010',
    borderBottomColor: '#2b2b2b',
    borderBottomWidth: 1,
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
    backgroundColor: '#161616',
    borderColor: '#5a5a5a',
    borderRadius: 8,
    borderWidth: 1,
    height: 32,
    justifyContent: 'center',
    width: 32,
  },
  stepDotActive: {
    backgroundColor: '#ffd21e',
    borderColor: '#ffd21e',
  },
  stepDotDone: {
    backgroundColor: '#2a2108',
    borderColor: '#ffae24',
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
    color: '#ffd21e',
  },
  stepText: {
    color: '#8d8a82',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textAlign: 'center',
  },
  stepTextActive: {
    color: '#fff8d6',
  },
  stepCard: {
    borderColor: '#333333',
    borderRadius: 8,
    borderWidth: 1,
    margin: 20,
    padding: 16,
  },
  sectionTitle: {
    color: '#ffae24',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
    marginBottom: 8,
    textTransform: 'uppercase',
  },
  helperText: {
    color: '#c3beb2',
    fontSize: 14,
    letterSpacing: 0,
    lineHeight: 20,
  },
  machineList: {
    gap: 10,
    marginTop: 16,
  },
  machineRow: {
    alignItems: 'center',
    backgroundColor: '#151515',
    borderColor: '#333333',
    borderRadius: 8,
    borderWidth: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    minHeight: 74,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  machineRowSelected: {
    backgroundColor: '#1b170b',
    borderColor: '#ffd21e',
    borderWidth: 2,
  },
  machineInfo: {
    flex: 1,
    paddingRight: 12,
  },
  machineName: {
    color: '#fff8d6',
    fontSize: 17,
    fontWeight: '800',
    letterSpacing: 0,
  },
  machineMeta: {
    color: '#aaa59a',
    fontSize: 14,
    letterSpacing: 0,
    marginTop: 3,
  },
  selectLabel: {
    color: '#ffae24',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0,
  },
  selectLabelSelected: {
    color: '#ffd21e',
  },
  previewImage: {
    backgroundColor: '#111111',
    borderRadius: 8,
    height: 230,
    marginTop: 16,
    width: '100%',
  },
  previewEmpty: {
    alignItems: 'center',
    backgroundColor: '#101010',
    borderColor: '#333333',
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
    backgroundColor: '#ffd21e',
    borderRadius: 8,
    flex: 1,
    justifyContent: 'center',
    minHeight: 50,
    paddingHorizontal: 14,
  },
  secondaryButton: {
    alignItems: 'center',
    borderColor: '#ffd21e',
    borderRadius: 8,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 50,
    paddingHorizontal: 14,
  },
  fullButton: {
    alignItems: 'center',
    backgroundColor: '#ffd21e',
    borderRadius: 8,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 50,
    paddingHorizontal: 14,
  },
  buttonDisabled: {
    backgroundColor: '#343434',
    borderColor: '#343434',
  },
  buttonPressed: {
    opacity: 0.86,
  },
  pressed: {
    opacity: 0.9,
  },
  buttonText: {
    color: '#111111',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
  },
  secondaryButtonText: {
    color: '#ffd21e',
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
    color: '#fff8d6',
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
    backgroundColor: '#10180d',
    borderColor: '#68d86f',
  },
  qualityWarn: {
    backgroundColor: '#211a08',
    borderColor: '#ffd21e',
  },
  qualityFail: {
    backgroundColor: '#220d0a',
    borderColor: '#ff6b3d',
  },
  qualityTitle: {
    color: '#fff8d6',
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
    backgroundColor: '#101010',
    borderColor: '#333333',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 16,
    padding: 12,
  },
  detectedLabel: {
    color: '#8d8a82',
    fontSize: 12,
    fontWeight: '900',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  detectedText: {
    color: '#ffd21e',
    fontSize: 22,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 5,
  },
  detectedSubText: {
    color: '#fff8d6',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0,
    marginTop: 4,
  },
  inputGrid: {
    gap: 12,
    marginTop: 14,
  },
  inputGroup: {
    flex: 1,
  },
  inputLabel: {
    color: '#8d8a82',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    marginBottom: 6,
    textTransform: 'uppercase',
  },
  input: {
    backgroundColor: '#101010',
    borderColor: '#353535',
    borderRadius: 8,
    borderWidth: 1,
    color: '#fff8d6',
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
  resultPanel: {
    borderColor: '#414141',
    borderRadius: 8,
    borderWidth: 1,
    marginTop: 12,
  },
  resultHeader: {
    alignItems: 'center',
    backgroundColor: '#111111',
    borderBottomColor: '#333333',
    borderBottomWidth: 1,
    borderTopLeftRadius: 8,
    borderTopRightRadius: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    minHeight: 70,
    paddingHorizontal: 16,
  },
  kicker: {
    color: '#8d8a82',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0,
    textTransform: 'uppercase',
  },
  resultTitle: {
    color: '#fff8d6',
    fontSize: 19,
    fontWeight: '900',
    letterSpacing: 0,
    marginTop: 3,
  },
  badge: {
    backgroundColor: '#211a08',
    borderColor: '#ffd21e',
    borderRadius: 8,
    borderWidth: 1,
    color: '#ffd21e',
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
    backgroundColor: '#101010',
    borderColor: '#333333',
    borderRadius: 8,
    borderWidth: 1,
    padding: 12,
  },
  matchCardHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  matchCode: {
    color: '#ffd21e',
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
    color: '#fff8d6',
    fontSize: 14,
    fontWeight: '900',
    letterSpacing: 0,
  },
  matchTitle: {
    color: '#fff8d6',
    fontSize: 16,
    fontWeight: '900',
    letterSpacing: 0,
    lineHeight: 22,
    marginTop: 10,
  },
  textBlock: {
    marginTop: 14,
  },
  textLabel: {
    color: '#ffae24',
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
    borderColor: '#333333',
    borderRadius: 8,
    borderWidth: 1,
    justifyContent: 'center',
    marginTop: 16,
    minHeight: 132,
    padding: 18,
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
