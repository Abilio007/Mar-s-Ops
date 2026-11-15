import { backendApi } from './backend-api.js';

function assertPushSupport() {
  if (
    !window.isSecureContext
    || !('serviceWorker' in navigator)
    || !('PushManager' in window)
    || !('Notification' in window)
  ) {
    throw new Error('Notificações do navegador exigem HTTPS (ou localhost) e suporte a Web Push.');
  }
}

function decodeApplicationServerKey(encodedKey) {
  const padding = '='.repeat((4 - encodedKey.length % 4) % 4);
  const base64 = (encodedKey + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  return Uint8Array.from(raw, (character) => character.charCodeAt(0));
}

export async function getBrowserPushSubscription() {
  if (
    !window.isSecureContext
    || !('serviceWorker' in navigator)
    || !('PushManager' in window)
    || !('Notification' in window)
  ) {
    return null;
  }

  const registration = await navigator.serviceWorker.ready;
  return registration.pushManager.getSubscription();
}

export async function enableBrowserPush(preferences) {
  assertPushSupport();
  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    throw new Error(permission === 'denied'
      ? 'A permissão foi bloqueada no navegador. Altere-a nas definições do site.'
      : 'A permissão para notificações não foi concedida.');
  }

  const publicKey = await backendApi.getPushVapidPublicKey();
  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription()
    || await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: decodeApplicationServerKey(publicKey),
    });

  await backendApi.savePushSubscription(subscription.toJSON(), preferences);
  return subscription;
}

export async function disableBrowserPush() {
  const subscription = await getBrowserPushSubscription();
  if (!subscription) return;

  await backendApi.deletePushSubscription(subscription.endpoint);
  await subscription.unsubscribe();
}

export async function updateBrowserPushPreferences(preferences) {
  if (!backendApi.hasToken) return;
  const subscription = await getBrowserPushSubscription();
  if (subscription) {
    await backendApi.savePushSubscription(subscription.toJSON(), preferences);
  }
}
