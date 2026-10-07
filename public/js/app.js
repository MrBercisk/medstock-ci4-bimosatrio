const { createApp, ref, reactive, onMounted } = Vue;
const NEW_BATCH = '__new__';
const LOGIN_URL = '/login';

// Label peran untuk ditampilkan di header
const ROLE_LABELS = {
  receiving_officer: 'Petugas Penerimaan',
  pharmacy_supervisor: 'Supervisor Farmasi',
};

function formatRole(role) {
  return ROLE_LABELS[role] || role;   // peran tak dikenal tampil apa adanya
}

createApp({
  setup() {
    // State
    const isCheckingSession = ref(true);   // true saat halaman pertama dimuat
    const currentUser = ref(null);

    // State: tampilan
    const currentView = ref('list');       // 'list' | 'detail' | 'form' | 'stock'
    const receipts = ref([]);
    const selectedReceipt = ref(null);
    const errorMessage = ref('');
    const successMessage = ref('');

    // State: form penerimaan
    const suppliers = ref([]);
    const medicines = ref([]);
    const knownBatches = ref([]);

    // State: form stock
    const stocks = ref([]);
    const expandedMedicineIds = ref([]);   // obat yang rincian batch-nya sedang dibuka

    const editingReceiptId = ref(null);    // null = penerimaan baru
    const receiptForm = reactive({ reference_no: '', supplier_id: '', received_at: '', items: [] });
    const isSaving = ref(false);
    const formError = ref('');
    const formErrorList = ref([]);
    let nextRowKey = 1;

    // Pindah ke halaman login (replace: tombol Back tidak kembali ke halaman ini)
    function goToLogin() {
      window.location.replace(LOGIN_URL);
    }

    // Penanganan error
    // 401 berarti sesi habis, kembali ke halaman login.
    function handleApiError(result, fallbackMessage) {
      if (result.status === 401) {
        goToLogin();
        return;
      }
      errorMessage.value = result.body.message || fallbackMessage;
    }

    // Sesi
    async function loadCurrentUser() {
      const result = await apiRequest('/me');
      currentUser.value = result.ok ? result.body.data : null;
    }

    async function logout() {
      await apiRequest('/logout', { method: 'POST' });
      goToLogin();
    }

    // Daftar dan detail penerimaan
    async function loadReceipts() {
      const result = await apiRequest('/receipts');

      if (result.ok) receipts.value = result.body.data;
      else handleApiError(result, 'Gagal memuat daftar penerimaan.');
    }

    async function showReceiptList() {
      errorMessage.value = '';
      successMessage.value = '';
      currentView.value = 'list';
      selectedReceipt.value = null;
      await loadReceipts();
    }

    async function showReceiptDetail(receiptId) {
      errorMessage.value = '';
      successMessage.value = '';
      const result = await apiRequest('/receipts/' + receiptId);

      if (result.ok) {
        selectedReceipt.value = result.body.data;
        currentView.value = 'detail';
      } else {
        handleApiError(result, 'Gagal memuat detail penerimaan.');
      }
    }

    // Laporan stok (tanpa on_date: server memakai hari ini di Jakarta)
    async function loadStocks() {
      const result = await apiRequest('/stocks');

      if (result.ok) stocks.value = result.body.data.medicines;
      else handleApiError(result, 'Gagal memuat laporan stok.');
    }

    async function showStockList() {
      errorMessage.value = '';
      successMessage.value = '';
      expandedMedicineIds.value = [];
      currentView.value = 'stock';
      await loadStocks();
    }

    function toggleStockDetail(medicineId) {
      const opened = expandedMedicineIds.value;
      expandedMedicineIds.value = opened.includes(medicineId)
        ? opened.filter((id) => id !== medicineId)
        : [...opened, medicineId];
    }

    function isStockExpanded(medicineId) {
      return expandedMedicineIds.value.includes(medicineId);
    }

    // Form Penerimaan
    // Pemasok dan obat aktif dimuat sekali, lalu dipakai ulang.
    async function loadLookups() {
      const [supplierResult, medicineResult, batchResult] = await Promise.all([
        apiRequest('/suppliers'),
        apiRequest('/medicines'),
        apiRequest('/batches'),
      ]);

      const failed = [supplierResult, medicineResult, batchResult].find((r) => !r.ok);
      if (failed) {
        handleApiError(failed, 'Gagal memuat data pilihan form.');
        return false;
      }

      suppliers.value = supplierResult.body.data;
      medicines.value = medicineResult.body.data;
      knownBatches.value = batchResult.body.data;
      return true;
    }

    function createEmptyItem() {
      return {
        rowKey: nextRowKey++,
        medicine_id: '', batch_no: '', isNewBatch: false,
        expires_on: '', quantity: '',
      };
    }

    function addItemRow() {
      receiptForm.items.push(createEmptyItem());
    }

    function removeItemRow(index) {
      receiptForm.items.splice(index, 1);
    }

    function unitOfMedicine(medicineId) {
      const medicine = medicines.value.find((m) => m.id === medicineId);
      return medicine ? medicine.unit : '';
    }

    // Batch yang sudah dikenal untuk satu obat (saran di kolom batch)
    function batchesOfMedicine(medicineId) {
      return knownBatches.value.filter((batch) => batch.medicine_id === medicineId);
    }

    // Ganti obat: batch dan kedaluwarsa dikosongkan karena daftar batch berbeda per obat
    function onMedicineChange(item) {
      item.batch_no = '';
      item.isNewBatch = false;
      item.expires_on = '';
    }

    // Pilihan di dropdown batch: batch lama (kedaluwarsa terisi otomatis) atau "Batch baru..."
    function onBatchChoice(item, chosenValue) {
      if (chosenValue === NEW_BATCH) {
        item.isNewBatch = true;
        item.batch_no = '';
        item.expires_on = '';
        return;
      }

      item.isNewBatch = false;
      item.batch_no = chosenValue;

      const known = batchesOfMedicine(item.medicine_id)
        .find((batch) => batch.batch_no === chosenValue);
      item.expires_on = known ? known.expires_on : '';
    }

    function clearFormErrors() {
      formError.value = '';
      formErrorList.value = [];
    }

    async function showNewReceiptForm() {
      errorMessage.value = '';
      successMessage.value = '';
      if (!(await loadLookups())) return;

      clearFormErrors();
      editingReceiptId.value = null;
      receiptForm.reference_no = '';
      receiptForm.supplier_id = '';
      receiptForm.received_at = nowInJakartaForInput();
      receiptForm.items = [createEmptyItem()];
      currentView.value = 'form';
    }

    // `receipt` boleh dari daftar maupun detail; keduanya memuat items.
    async function showEditReceiptForm(receipt) {
      errorMessage.value = '';
      successMessage.value = '';
      if (!(await loadLookups())) return;

      clearFormErrors();
      editingReceiptId.value = receipt.id;
      receiptForm.reference_no = receipt.reference_no;
      receiptForm.supplier_id = receipt.supplier.id;
      receiptForm.received_at = toInputDateTime(receipt.received_at);
      receiptForm.items = receipt.items.map((item) => ({
        rowKey: nextRowKey++,
        medicine_id: item.medicine_id,
        batch_no: item.batch_no,
        isNewBatch: false,
        expires_on: item.expires_on,
        quantity: item.quantity,
      }));
      currentView.value = 'form';
    }

    // Bentuk data persis seperti yang diminta API (tanpa field khusus tampilan).
    function buildReceiptPayload() {
      return {
        reference_no: receiptForm.reference_no,
        supplier_id: receiptForm.supplier_id === '' ? null : receiptForm.supplier_id,
        received_at: toApiDateTime(receiptForm.received_at),
        items: receiptForm.items.map((item) => ({
          medicine_id: item.medicine_id === '' ? null : item.medicine_id,
          batch_no: item.batch_no,
          expires_on: item.expires_on,
          quantity: item.quantity === '' ? null : Number(item.quantity),
        })),
      };
    }

    async function saveReceipt() {
      isSaving.value = true;
      clearFormErrors();

      const isEditing = editingReceiptId.value !== null;
      const result = await apiRequest(
        isEditing ? '/receipts/' + editingReceiptId.value : '/receipts',
        { method: isEditing ? 'PUT' : 'POST', body: buildReceiptPayload() }
      );

      if (result.ok) {
        selectedReceipt.value = result.body.data;
        successMessage.value = result.body.message;
        currentView.value = 'detail';
      } else if (result.status === 401) {
        goToLogin();
        return;
      } else {
        formError.value = result.body.message || 'Gagal menyimpan penerimaan.';
        formErrorList.value = Object.values(result.body.errors || {}).map(String);
      }

      isSaving.value = false;
    }

    // Saat halaman dimuat
    onMounted(async () => {
      await loadCurrentUser();
      if (!currentUser.value) {
        goToLogin();   // belum login: langsung ke halaman login
        return;
      }
      await loadReceipts();
      isCheckingSession.value = false;
    });

    // Yang dipakai template
    return {
      isCheckingSession, currentUser, logout, formatRole,

      currentView, receipts, selectedReceipt, errorMessage, successMessage,
      showReceiptList, showReceiptDetail,

      suppliers, medicines, editingReceiptId, receiptForm,
      stocks, showStockList, toggleStockDetail, isStockExpanded,
      isSaving, formError, formErrorList,

      showNewReceiptForm,
      showEditReceiptForm,
      addItemRow,
      removeItemRow,
      unitOfMedicine,
      batchesOfMedicine,
      saveReceipt,

      onMedicineChange,
      onBatchChoice,
      NEW_BATCH,

      formatDateTime,
      formatDate,
      formatAction,
    };
  },
}).mount('#app');
