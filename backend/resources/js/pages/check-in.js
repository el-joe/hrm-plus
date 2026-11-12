/**
 * Check-in / check-out from the employee portal ([data-check-in], employee/_check-in.blade.php).
 *
 * 1. Location: navigator.geolocation (high accuracy, 20 s timeout). Browsers only expose it in a
 *    secure context (HTTPS or localhost) — window.isSecureContext is checked first so the employee
 *    gets an explanation instead of a silent failure. When the policy does not require the geofence
 *    on the web, a missing position is not fatal (the server records what it gets).
 * 2. Face (when the policy requires it, or offers it and the employee is enrolled): getUserMedia
 *    camera preview in a dialog → canvas → JPEG base64.
 * 3. POST JSON to portal.attendance.check-in|check-out with request_uuid, device_time, position and
 *    face. The uuid is reused when retrying after a network error / timeout (the server answers with
 *    the stored outcome, nothing is counted twice) and replaced after any decision.
 *
 * Success is shown only for 201 (recorded); 202 = sent for review (not recorded); 422 = refused with
 * the server's reason. The page reloads after a decision that changed today's state.
 */
import { request } from '../lib/http';
import { formatDigits } from '../lib/format';
import { openModal, closeModal } from '../ui/modal';

const REQUEST_TIMEOUT_MS = 30000;
const GEO_TIMEOUT_MS = 20000;

const TONES = {
    success: ['bg-feedback-success-bg', 'text-feedback-success-text'],
    info: ['bg-feedback-info-bg', 'text-feedback-info-text'],
    warning: ['bg-feedback-warning-bg', 'text-feedback-warning-text'],
    danger: ['bg-feedback-danger-bg', 'text-feedback-danger-text'],
    neutral: ['bg-surface-muted', 'text-text-secondary'],
};

/** RFC 4122 v4; crypto.randomUUID only exists in secure contexts. */
function uuid() {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function replace(text, values = {}) {
    return Object.entries(values).reduce((result, [name, value]) => result.replaceAll(`:${name}`, String(value)), text ?? '');
}

class LocationError extends Error {
    constructor(key) {
        super(key);
        this.key = key;
    }
}

function locate() {
    if (!window.isSecureContext) {
        return Promise.reject(new LocationError('insecure'));
    }

    if (!('geolocation' in navigator)) {
        return Promise.reject(new LocationError('unsupported'));
    }

    return new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(
            (position) => resolve(position.coords),
            (error) => reject(new LocationError({ 1: 'permission_denied', 2: 'position_unavailable', 3: 'timeout' }[error.code] ?? 'position_unavailable')),
            { enableHighAccuracy: true, timeout: GEO_TIMEOUT_MS, maximumAge: 0 },
        );
    });
}

function initCheckIn(root) {
    const config = JSON.parse(root.querySelector('[data-check-in-config]')?.textContent ?? '{}');
    const strings = config.strings ?? {};
    const button = root.querySelector('[data-check-in-submit]');
    const status = root.querySelector('[data-check-in-status]');
    const accuracyChip = root.querySelector('[data-check-in-accuracy]');
    const camera = config.camera_id ? document.getElementById(config.camera_id) : null;

    if (!button || !status) {
        return;
    }

    let pendingUuid = null;
    let busy = false;

    const say = (key, tone = 'info', values = {}) => {
        const text = strings[key] !== undefined ? replace(strings[key], values) : key;
        status.textContent = formatDigits(text);
        status.classList.remove('hidden', ...Object.values(TONES).flat());
        status.classList.add(...(TONES[tone] ?? TONES.info));
        status.dataset.checkInStatus = tone;
    };

    const setBusy = (value) => {
        busy = value;
        button.disabled = value;
        button.toggleAttribute('data-loading', value);
        button.setAttribute('aria-busy', String(value));
    };

    const offerRetry = () => {
        const label = button.querySelector('span:last-of-type');

        if (label && strings.retry) {
            label.textContent = strings.retry;
        }
    };

    const showAccuracy = (meters) => {
        if (!accuracyChip) {
            return;
        }

        accuracyChip.textContent = formatDigits(replace(strings.accuracy, { meters: Math.round(meters) }));
        accuracyChip.hidden = false;
    };

    async function captureFace() {
        if (!camera || !window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            throw new LocationError(window.isSecureContext ? 'camera_unavailable' : 'insecure');
        }

        const video = camera.querySelector('[data-check-in-video]');
        const canvas = camera.querySelector('[data-check-in-canvas]');
        const capture = camera.querySelector('[data-check-in-capture]');
        const cameraStatus = camera.querySelector('[data-check-in-camera-status]');
        let stream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
        } catch (error) {
            throw new LocationError(error?.name === 'NotAllowedError' || error?.name === 'SecurityError' ? 'camera_denied' : 'camera_unavailable');
        }

        video.srcObject = stream;
        capture.disabled = true;
        cameraStatus.hidden = false;
        video.addEventListener('loadeddata', () => {
            capture.disabled = false;
            cameraStatus.hidden = true;
        }, { once: true });
        openModal(camera, button);

        try {
            return await new Promise((resolve, reject) => {
                const done = (result, error = null) => {
                    camera.removeEventListener('click', onClick);
                    camera.removeEventListener('close', onClose);
                    error ? reject(error) : resolve(result);
                };
                const onClick = (event) => {
                    if (event.target.closest('[data-check-in-capture]')) {
                        canvas.width = video.videoWidth || 640;
                        canvas.height = video.videoHeight || 480;
                        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                        done({ images: [canvas.toDataURL('image/jpeg', 0.85).split(',')[1]] });
                        closeModal(camera, 'captured');
                    } else if (event.target.closest('[data-check-in-camera-skip]')) {
                        done(null);
                        closeModal(camera, 'skipped');
                    } else if (event.target.closest('[data-check-in-camera-cancel]')) {
                        closeModal(camera, 'cancel');
                    }
                };
                const onClose = () => {
                    if (!['captured', 'skipped'].includes(camera.returnValue)) {
                        done(null, new LocationError('cancelled'));
                    }
                };

                camera.returnValue = '';
                camera.addEventListener('click', onClick);
                camera.addEventListener('close', onClose);
            });
        } finally {
            stream.getTracks().forEach((track) => track.stop());
            video.srcObject = null;
        }
    }

    async function send(payload) {
        pendingUuid ??= uuid();
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
        const url = config.urls[config.action];

        say('sending', 'neutral');

        try {
            const { response, body } = await request(url, { method: 'POST', data: { ...payload, request_uuid: pendingUuid }, signal: controller.signal });

            if (response.status !== 201 && response.status !== 202) {
                // Anything else (e.g. a login page after a redirect) is not a decision.
                say('session_expired', 'danger');

                return false;
            }

            pendingUuid = null;

            if (response.status === 201) {
                say(body.message, 'success');
            } else {
                say(body.message, 'info');
            }

            setTimeout(() => window.location.reload(), response.status === 201 ? 1500 : 3000);

            return true;
        } catch (error) {
            if (error?.name === 'AbortError' || error?.status === 0) {
                // Same uuid on the next tap: the server replays the stored outcome if it got the request.
                say(navigator.onLine ? 'network' : 'offline', 'danger');
            } else if (error.status === 419 || error.status === 401) {
                say('session_expired', 'danger');
            } else if (error.status === 429) {
                say('throttled', 'warning');
            } else if (error.status >= 500) {
                say('server_error', 'danger');
            } else {
                pendingUuid = null;
                rejected(error);
            }

            return false;
        } finally {
            clearTimeout(timer);
        }
    }

    function rejected(error) {
        const data = error.body?.data ?? {};
        const code = data.reasons?.[0]?.code ?? null;

        if (code === 'face_retry') {
            say('face_retry', 'warning', { count: data.face_remaining_attempts ?? 0 });

            return;
        }

        let message = error.message;

        if (code === 'outside_geofence' && data.location?.distance_meters != null) {
            message += ' ' + replace(strings.outside_geofence, {
                distance: data.location.distance_meters,
                location: data.location.name ?? '—',
                radius: data.location.allowed_radius_meters ?? 0,
            });
        }

        say(message, 'danger');
    }

    async function run() {
        if (busy) {
            return;
        }

        setBusy(true);

        try {
            if (!navigator.onLine) {
                say('offline', 'danger');

                return;
            }

            const payload = { device_time: new Date().toISOString() };

            say('locating', 'neutral');

            try {
                const coords = await locate();
                showAccuracy(coords.accuracy);

                if (config.requires_location && coords.accuracy > config.max_accuracy) {
                    say('poor_accuracy', 'warning', { meters: Math.round(coords.accuracy), max: config.max_accuracy });

                    return;
                }

                Object.assign(payload, { latitude: coords.latitude, longitude: coords.longitude, accuracy: coords.accuracy });
                say('located', 'neutral', { meters: Math.round(coords.accuracy) });
            } catch (error) {
                // Without a required geofence the server accepts a check-in without a position.
                if (config.requires_location || !(error instanceof LocationError)) {
                    say(error.key ?? 'position_unavailable', 'danger');

                    return;
                }
            }

            if (config.face?.capture) {
                try {
                    const face = await captureFace();

                    if (face) {
                        payload.face = face;
                    }
                } catch (error) {
                    if (error.key === 'cancelled') {
                        status.classList.add('hidden');

                        return;
                    }

                    // An optional face that cannot be captured is simply not sent.
                    if (!config.face.optional) {
                        say(error.key ?? 'camera_unavailable', 'danger');

                        return;
                    }
                }
            }

            if (await send(payload)) {
                return;
            }
        } finally {
            if (status.dataset.checkInStatus !== 'success' && status.dataset.checkInStatus !== 'info') {
                offerRetry();
            }

            setBusy(status.dataset.checkInStatus === 'success' || status.dataset.checkInStatus === 'info');
        }
    }

    button.addEventListener('click', run);
}

export function initCheckIns() {
    document.querySelectorAll('[data-check-in]').forEach((root) => initCheckIn(root));
}
