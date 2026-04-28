FROM node:22-bookworm-slim AS build

WORKDIR /app

ARG EXPO_PUBLIC_API_URL
ENV EXPO_PUBLIC_API_URL=${EXPO_PUBLIC_API_URL}
ENV EXPO_NO_TELEMETRY=1

COPY apps/mobile/package*.json ./
RUN npm ci

COPY apps/mobile ./
RUN npx expo export --platform web

FROM nginx:1.29-alpine

COPY docker/frontend/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
