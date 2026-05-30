import { config } from './config.js';

/**
 * Constant-time bearer-token check. Laravel and the sidecar share one secret
 * (SIDECAR_AUTH_TOKEN). The sidecar binds to loopback only, but we still
 * check the token to defend against same-host process abuse.
 */
export function requireBearer(req, res, next) {
  const header = req.get('authorization') ?? '';
  const presented = header.startsWith('Bearer ') ? header.slice(7) : '';
  const expected = config.authToken;

  if (presented.length !== expected.length) {
    return res.status(401).json({ error: 'unauthorized' });
  }
  let mismatch = 0;
  for (let i = 0; i < expected.length; i++) {
    mismatch |= presented.charCodeAt(i) ^ expected.charCodeAt(i);
  }
  if (mismatch !== 0) {
    return res.status(401).json({ error: 'unauthorized' });
  }
  next();
}
