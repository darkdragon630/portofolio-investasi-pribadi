/**
 * SAZEN Investment Portfolio Manager v3.0
 * Form Keuntungan - JavaScript Handler
 */

// ===================================
// INVESTMENT SELECTION HANDLER
// ===================================
document.getElementById('investasi_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const info = document.getElementById('investmentInfo');
    
    if (selected.value) {
        // Update kategori
        document.getElementById('selectedCategory').textContent = selected.dataset.namaKategori;
        document.getElementById('kategori_id').value = selected.dataset.kategori;
        
        // Update amount
        const amount = parseFloat(selected.dataset.jumlah || 0);
        if (amount > 0) {
            document.getElementById('selectedAmount').textContent = amount.toLocaleString('id-ID');
            document.getElementById('investmentAmountContainer').style.display = 'block';
        } else {
            document.getElementById('investmentAmountContainer').style.display = 'none';
        }
        
        info.classList.add('show');
    } else {
        info.classList.remove('show');
    }
});


// ===================================
// AUTO-CALCULATE PERCENTAGE
// ===================================
document.getElementById('jumlah_keuntungan').addEventListener('blur', function() {
    const select = document.getElementById('investasi_id');
    const selected = select.options[select.selectedIndex];
    const pctInput = document.getElementById('persentase_keuntungan');
    
    // Skip if user already entered percentage
    if (pctInput.value.trim() !== '') return;
    
    const amount = parseFloat(selected?.dataset.jumlah || 0);
    const profitRaw = this.value.trim();
    
    if (!profitRaw || amount <= 0) return;
    
    // Parse profit (remove dots and replace comma with dot)
    const profit = parseFloat(
    profitRaw
        .replace(/[^\d,.]/g, '')
        .replace(/\./g, '')
        .replace(/,/g, '.')
) || 0;
    
    if (profit >= 0) {
        const percentage = (profit / amount) * 100;
        pctInput.value = percentage.toFixed(2);
    }
});


// ===================================
// CURRENCY INPUT FORMATTER
// ===================================
const jumlahInput = document.getElementById('jumlah_keuntungan');

// Blur: format ribuan saja, tidak hapus koma desimal
jumlahInput.addEventListener('blur', function() {
    let v = this.value
        .replace(/[^\d,.]/g, '')     // buang selain digit, koma, titik
        .replace(/,/g, '#')          // tandai koma asli
        .replace(/\./g, '')          // buang titik ribuan sementara
        .replace(/#/g, '.');         // kembalikan koma jadi titik desimal

    const num = parseFloat(v) || 0;
    this.value = num.toLocaleString('id-ID', { minimumFractionDigits: 2 });
});

// Focus: kembalikan ke bentuk asli
jumlahInput.addEventListener('focus', function() {
    this.value = this.value
        .replace(/[^\d,.]/g, '')
        .replace(/\./g, '')   // buang titik ribuan
        .replace(/,/g, '.');  // koma jadi titik desimal
});


// ===================================
// FILE UPLOAD PREVIEW
// ===================================
document.getElementById('bukti_file').addEventListener('change', function() {
    previewFile(this);
});

function previewFile(input) {
    const preview = document.getElementById('filePreview');
    const file = input.files[0];
    
    if (!file) {
        preview.innerHTML = '';
        preview.style.display = 'none';
        return;
    }
    
    const fileSize = (file.size / 1024 / 1024).toFixed(2);
    const fileType = file.type;
    
    // Check file size (Max 5MB)
    if (fileSize > 5) {
        alert('File terlalu besar! Maksimal 5MB');
        input.value = '';
        preview.innerHTML = '';
        return;
    }
    
    const reader = new FileReader();
    
    reader.onload = function(e) {
        let previewHTML = '';
        
        if (fileType.startsWith('image/')) {
            // Image preview
            previewHTML = `
                <div class="preview-card">
                    <img src="${e.target.result}" alt="Preview">
                    <div class="preview-info">
                        <strong>${file.name}</strong>
                        <span>${fileSize} MB</span>
                    </div>
                    <button type="button" onclick="removeFile()" class="remove-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        } else if (fileType === 'application/pdf') {
            // PDF preview
            previewHTML = `
                <div class="preview-card">
                    <div class="pdf-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="preview-info">
                        <strong>${file.name}</strong>
                        <span>${fileSize} MB</span>
                    </div>
                    <button type="button" onclick="removeFile()" class="remove-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }
        
        preview.innerHTML = previewHTML;
        preview.style.display = 'block';
    };
    
    reader.readAsDataURL(file);
}

function removeFile() {
    const input = document.getElementById('bukti_file');
    const preview = document.getElementById('filePreview');
    input.value = '';
    preview.innerHTML = '';
    preview.style.display = 'none';
}


// ===================================
// DRAG & DROP HANDLER
// ===================================
const fileLabel = document.querySelector('.file-label');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    fileLabel.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    fileLabel.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    fileLabel.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    fileLabel.classList.add('drag-over');
}

function unhighlight() {
    fileLabel.classList.remove('drag-over');
}

fileLabel.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    const input = document.getElementById('bukti_file');
    input.files = files;
    previewFile(input);
}


// ===================================
// FORM VALIDATION
// ===================================
document.querySelector('.data-form').addEventListener('submit', function(e) {
    const raw = document.getElementById('jumlah_keuntungan').value
                 .replace(/[^\d,.]/g, '')
                 .replace(/\./g, '')
                 .replace(/,/g, '.');
    const jumlah = parseFloat(raw) || 0;

    if (jumlah <= 0) {
        e.preventDefault();
        alert('Jumlah keuntungan harus diisi dan tidak boleh negatif!');
        return false;
    }
});


// ===================================
// RADIO CARD SELECTION
// ===================================
document.querySelectorAll('.radio-card input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const group = this.name;
        
        // Remove 'selected' class from all radios in the same group
        document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
            r.closest('.radio-card').classList.remove('selected');
        });
        
        // Add 'selected' class to checked radio
        if (this.checked) {
            this.closest('.radio-card').classList.add('selected');
        }
    });
    
    // Initialize selected state for checked radios
    if (radio.checked) {
        radio.closest('.radio-card').classList.add('selected');
    }
});


// ===================================
// SET DEFAULT DATE (TODAY)
// ===================================
document.getElementById('tanggal_keuntungan').value = new Date().toISOString().split('T')[0];
