// Satu pintu untuk semua request ke API.
// Cookie sesi selalu dikirim, respons selalu dibaca sebagai JSON.
// `body` cukup berupa objek biasa; di sini diubah jadi JSON.
async function apiRequest(path, { method = 'GET', body } = {}) {
  const response = await fetch('/api' + path, {
    method,
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });

  const json = await response.json().catch(() => ({}));

  return { ok: response.ok, status: response.status, body: json };
}
