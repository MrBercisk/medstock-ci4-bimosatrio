const JAKARTA = 'Asia/Jakarta';

// "2026-10-03T10:00:00+07:00" -> "3 Okt 2026, 10.00"
function formatDateTime(isoString) {
  return new Date(isoString).toLocaleString('id-ID', {
    timeZone: JAKARTA, dateStyle: 'medium', timeStyle: 'short',
  });
}

// "2027-12-31" -> "31 Des 2027"
function formatDate(ymd) {
  return new Date(ymd + 'T00:00:00+07:00').toLocaleDateString('id-ID', {
    timeZone: JAKARTA, dateStyle: 'medium',
  });
}

// "create" / "update" -> label untuk riwayat
function formatAction(action) {
  const labels = { create: 'Dibuat', update: 'Diubah' };
  return labels[action] ?? action;
}

// Waktu sekarang di Jakarta, format untuk <input type="datetime-local">: "2026-10-07T01:11"
function nowInJakartaForInput() {
  return new Date()
    .toLocaleString('sv-SE', { timeZone: JAKARTA })
    .replace(' ', 'T')
    .slice(0, 16);
}

// "2026-10-07T01:11" -> "2026-10-07T01:11:00+07:00" (format yang diminta API)
function toApiDateTime(inputValue) {
  return inputValue + ':00+07:00';
}

// "2026-10-03T10:00:00+07:00" -> "2026-10-03T10:00" (API sudah memakai waktu Jakarta)
function toInputDateTime(isoString) {
  return isoString.slice(0, 16);
}
