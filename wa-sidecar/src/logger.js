import pino from 'pino';
import { config } from './config.js';

export const logger = pino({
  level: config.logLevel,
  base: { svc: 'wa-sidecar' },
  redact: {
    paths: ['req.headers.authorization', '*.authToken', '*.access_token'],
    censor: '[redacted]',
  },
});
