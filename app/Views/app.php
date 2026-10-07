<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MedStock</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/css/medstock.css">
  <style>[v-cloak] { display: none !important; }</style>
</head>
<body>
  <div id="app" v-cloak class="container py-4" style="max-width: 1000px">

    <!-- Header -->
    <header class="d-flex justify-content-between align-items-center mb-4">
      <div class="ms-brand">
        <span class="ms-brand-mark"><i class="bi bi-capsule-pill"></i></span>
        <span class="fs-5">MedStock</span>
      </div>
      <div v-if="currentUser" class="ms-user">
        <span class="d-none d-sm-inline">{{ currentUser.email }}</span>
        <span class="ms-pill ms-pill-role">{{ formatRole(currentUser.role) }}</span>
        <button class="btn btn-sm btn-outline-secondary" @click="logout">
          <i class="bi bi-box-arrow-right"></i> Keluar
        </button>
      </div>
    </header>

    <p v-if="isCheckingSession" class="text-muted">Memuat...</p>

    <main v-else-if="currentUser">
      <nav class="ms-tabs">
        <button class="ms-tab" :class="{ active: currentView !== 'stock' }" @click="showReceiptList">
          <i class="bi bi-box-seam me-1"></i> Penerimaan
        </button>
        <button class="ms-tab" :class="{ active: currentView === 'stock' }" @click="showStockList">
          <i class="bi bi-clipboard2-data me-1"></i> Stok
        </button>
      </nav>

      <div v-if="errorMessage" class="alert alert-danger py-2">{{ errorMessage }}</div>
      <div v-if="successMessage" class="alert alert-success py-2">{{ successMessage }}</div>

      <!-- Daftar penerimaan -->
      <section v-if="currentView === 'list'">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h1 class="h5 fw-bold mb-0">Penerimaan obat</h1>
          <button class="btn btn-primary btn-sm" @click="showNewReceiptForm">
            <i class="bi bi-plus-lg"></i> Penerimaan baru
          </button>
        </div>
        <div class="ms-card table-responsive">
          <table class="table ms-table align-middle">
            <thead>
              <tr>
                <th>No. referensi</th><th>Pemasok</th><th>Diterima</th>
                <th>Dibuat oleh</th><th>Item</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="receipts.length === 0">
                <td colspan="6" class="ms-empty">
                  <i class="bi bi-inbox"></i>Belum ada penerimaan. Klik "Penerimaan baru" untuk mencatat yang pertama.
                </td>
              </tr>
              <tr v-for="receipt in receipts" :key="receipt.id">
                <td class="ms-ref">{{ receipt.reference_no }}</td>
                <td>{{ receipt.supplier.name }}</td>
                <td>{{ formatDateTime(receipt.received_at) }}</td>
                <td>{{ receipt.created_by.name }}</td>
                <td>{{ receipt.items.length }}</td>
                <td class="text-end text-nowrap">
                  <button class="btn btn-sm btn-outline-primary" @click="showReceiptDetail(receipt.id)">Detail</button>
                  <button v-if="receipt.can_update" class="btn btn-sm btn-outline-secondary ms-1"
                          @click="showEditReceiptForm(receipt)">Edit</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Detail penerimaan -->
      <section v-else-if="currentView === 'detail' && selectedReceipt" class="ms-card">
        <div class="ms-card-body">
          <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
              <h1 class="h5 fw-bold mb-1">{{ selectedReceipt.reference_no }}</h1>
              <div class="text-muted small">{{ selectedReceipt.supplier.name }}</div>
            </div>
            <div class="d-flex gap-2">
              <button v-if="selectedReceipt.can_update" class="btn btn-sm btn-outline-secondary"
                      @click="showEditReceiptForm(selectedReceipt)">Edit</button>
              <button class="btn btn-sm btn-outline-dark" @click="showReceiptList">Kembali</button>
            </div>
          </div>

          <dl class="ms-meta">
            <div>
              <dt>Diterima</dt>
              <dd>{{ formatDateTime(selectedReceipt.received_at) }}</dd>
            </div>
            <div>
              <dt>Dibuat oleh</dt>
              <dd>{{ selectedReceipt.created_by.name }}
                <span class="fw-normal text-muted small d-block">{{ formatDateTime(selectedReceipt.created_at) }}</span>
              </dd>
            </div>
            <div>
              <dt>Diubah terakhir oleh</dt>
              <dd v-if="selectedReceipt.updated_by">{{ selectedReceipt.updated_by.name }}
                <span class="fw-normal text-muted small d-block">{{ formatDateTime(selectedReceipt.updated_at) }}</span>
              </dd>
              <dd v-else class="fw-normal text-muted">Belum pernah diubah</dd>
            </div>
          </dl>

          <h2 class="h6 fw-bold">Item</h2>
          <div class="table-responsive mb-4">
            <table class="table ms-table table-sm">
              <thead>
                <tr><th>Obat</th><th>Batch</th><th>Kedaluwarsa</th><th class="text-end">Jumlah</th></tr>
              </thead>
              <tbody>
                <tr v-for="item in selectedReceipt.items" :key="item.medicine_id + item.batch_no">
                  <td>{{ item.code }} - {{ item.name }}</td>
                  <td>{{ item.batch_no }}</td>
                  <td>{{ formatDate(item.expires_on) }}</td>
                  <td class="text-end fw-semibold">{{ item.quantity }} {{ item.unit }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <h2 class="h6 fw-bold">Riwayat</h2>
          <ul class="ms-timeline">
            <li v-for="(entry, index) in selectedReceipt.history" :key="index">
              {{ formatAction(entry.action) }} oleh {{ entry.user.name }}, {{ formatDateTime(entry.at) }}
            </li>
          </ul>
        </div>
      </section>

      <!-- Form penerimaan (tambah dan ubah) -->
      <section v-else-if="currentView === 'form'" class="ms-card">
        <div class="ms-card-body">
          <h1 class="h5 fw-bold mb-4">{{ editingReceiptId ? 'Ubah penerimaan' : 'Penerimaan baru' }}</h1>

          <div v-if="formError" class="alert alert-danger py-2">
            <div>{{ formError }}</div>
            <ul v-if="formErrorList.length" class="mb-0 mt-1">
              <li v-for="(message, index) in formErrorList" :key="index">{{ message }}</li>
            </ul>
          </div>

          <form @submit.prevent="saveReceipt">
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label">No. referensi</label>
                <input type="text" class="form-control" maxlength="50" v-model="receiptForm.reference_no">
              </div>
              <div class="col-md-4">
                <label class="form-label">Pemasok</label>
                <select class="form-select" v-model="receiptForm.supplier_id">
                  <option value="">Pilih pemasok</option>
                  <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                    {{ supplier.name }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Waktu diterima (WIB)</label>
                <input type="datetime-local" class="form-control" v-model="receiptForm.received_at">
              </div>
            </div>

            <h2 class="h6 fw-bold">Item</h2>
            <div v-for="(item, index) in receiptForm.items" :key="item.rowKey"
                 class="ms-item-row row g-2 align-items-end mx-0">
              <div class="col-md-4">
                <label class="form-label">Obat</label>
                <select class="form-select" v-model="item.medicine_id" @change="onMedicineChange(item)">
                  <option value="">Pilih obat</option>
                  <option v-for="medicine in medicines" :key="medicine.id" :value="medicine.id">
                    {{ medicine.code }} - {{ medicine.name }}
                  </option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Batch</label>
                <select class="form-select"
                        :value="item.isNewBatch ? NEW_BATCH : item.batch_no"
                        :disabled="!item.medicine_id"
                        @change="onBatchChoice(item, $event.target.value)">
                  <option value="">{{ item.medicine_id ? 'Pilih batch' : 'Pilih obat dulu' }}</option>
                  <option v-for="batch in batchesOfMedicine(item.medicine_id)" :key="batch.batch_no"
                          :value="batch.batch_no">{{ batch.batch_no }}</option>
                  <option :value="NEW_BATCH">Batch baru...</option>
                </select>
                <input v-if="item.isNewBatch" type="text" class="form-control mt-1"
                       placeholder="No. batch baru" v-model="item.batch_no">
              </div>
              <div class="col-md-3">
                <label class="form-label">Kedaluwarsa</label>
                <input type="date" class="form-control" v-model="item.expires_on">
              </div>
              <div class="col-md-2">
                <label class="form-label">Jumlah</label>
                <div class="input-group">
                  <input type="number" class="form-control" v-model="item.quantity">
                  <span v-if="item.medicine_id" class="input-group-text">{{ unitOfMedicine(item.medicine_id) }}</span>
                </div>
              </div>
              <div class="col-md-1 text-end">
                <button type="button" class="btn btn-outline-danger btn-sm" title="Hapus item"
                        :disabled="receiptForm.items.length === 1"
                        @click="removeItemRow(index)"><i class="bi bi-trash"></i></button>
              </div>
            </div>

            <button type="button" class="btn btn-sm btn-outline-primary mt-2 mb-4" @click="addItemRow">
              <i class="bi bi-plus-lg"></i> Tambah item
            </button>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" :disabled="isSaving">{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</button>
              <button type="button" class="btn btn-outline-dark" :disabled="isSaving"
                      @click="showReceiptList">Batal</button>
            </div>
          </form>
        </div>
      </section>

      <!-- Laporan stok -->
      <section v-else-if="currentView === 'stock'">
        <h1 class="h5 fw-bold mb-3">Stok obat</h1>
        <div class="ms-card table-responsive">
          <table class="table ms-table align-middle">
            <thead>
              <tr>
                <th>Kode</th><th>Obat</th><th>Satuan</th>
                <th class="text-end">Fisik</th>
                <th class="text-end">Tersedia</th>
                <th class="text-end">Kedaluwarsa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="stocks.length === 0">
                <td colspan="7" class="ms-empty"><i class="bi bi-clipboard2-x"></i>Tidak ada data stok.</td>
              </tr>
              <template v-for="medicine in stocks" :key="medicine.medicine_id">
                <tr>
                  <td class="ms-ref">{{ medicine.code }}</td>
                  <td>{{ medicine.name }}</td>
                  <td>{{ medicine.unit }}</td>
                  <td class="text-end">{{ medicine.physical_quantity }}</td>
                  <td class="text-end fw-bold">{{ medicine.available_quantity }}</td>
                  <td class="text-end" :class="{ 'ms-qty-bad': medicine.expired_quantity > 0 }">{{ medicine.expired_quantity }}</td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" @click="toggleStockDetail(medicine.medicine_id)">
                      {{ isStockExpanded(medicine.medicine_id) ? 'Tutup' : 'Detail' }}
                    </button>
                  </td>
                </tr>
                <tr v-if="isStockExpanded(medicine.medicine_id)">
                  <td colspan="7" class="bg-light">
                    <p v-if="medicine.available_batches.length === 0 && medicine.expired_batches.length === 0"
                       class="small text-muted mb-0">Belum ada batch.</p>
                    <div v-for="batch in medicine.available_batches" :key="'a' + batch.batch_no" class="ms-batch">
                      <span class="ms-pill ms-pill-ok">Tersedia</span>
                      <strong>{{ batch.batch_no }}</strong>
                      <span class="text-muted">kedaluwarsa {{ formatDate(batch.expires_on) }}</span>
                      <span class="ms-auto fw-semibold">{{ batch.quantity }} {{ medicine.unit }}</span>
                    </div>
                    <div v-for="batch in medicine.expired_batches" :key="'e' + batch.batch_no" class="ms-batch">
                      <span class="ms-pill ms-pill-bad">Kedaluwarsa</span>
                      <strong>{{ batch.batch_no }}</strong>
                      <span class="text-muted">kedaluwarsa {{ formatDate(batch.expires_on) }}</span>
                      <span class="ms-auto fw-semibold">{{ batch.quantity }} {{ medicine.unit }}</span>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
  <script src="/js/api.js"></script>
  <script src="/js/format.js"></script>
  <script src="/js/app.js"></script>
</body>
</html>
