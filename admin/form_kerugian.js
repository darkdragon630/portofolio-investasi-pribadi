/**
 * SAZEN Investment Portfolio Manager v3.0
 * Form Kerugian - JavaScript Handler - FIXED FOR ZERO INPUT
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
// AUTO-CALCULATE PERCENTAGE (FIXED)
// ===================================
document.getElementById('jumlah_kerugian').addEventListener('blur', function() {
    const select = document.getElementById('investasi_id');
    const selected = select.options[select.selectedIndex];
    const pctInput = document.getElementById('persentase_kerugian');
    
    // Skip if user already entered percentage
    if (pctInput.value.trim() !== '') return;
    
    const amount = parseFloat(selected?.dataset.jumlah || 0);
    const lossRaw = this.value.trim();
    
    // ✅ FIXED: Jangan skip kalau lossRaw = "0" atau "0,00"
    if (lossRaw === '' || amount <= 0) return;
    
    // Parse loss (remove dots and replace comma with dot)
    const loss = parseFloat(
        lossRaw
            .replace(/[^\d,.]/g, '')
            .replace(/\./g, '')
            .replace(/,/g, '.')
    );
    
    // ✅ FIXED: Pastikan NaN check dan terima nilai 0
    if (!isNaN(loss) && loss >= 0) {
        const percentage = amount > 0 ? (loss / amount) * 100 : 0;
        pctInput.value = percentage.toFixed(2);
    }
});


// ===================================
// CURRENCY INPUT FORMATTER (FIXED)
// ===================================
const jumlahInput = document.getElementById('jumlah_kerugian');

jumlahInput.addEventListener('blur', function() {
    let v = this.value
        .replace(/[^\d,.]/g, '')   // buang selain digit, koma, titik
        .replace(/,/g, '#')        // tandai koma asli
        .replace(/\./g, '')        // buang titik ribuan sementara
        .replace(/#/g, '.');       // kembalikan koma jadi titik desimal

    const num = parseFloat(v);
    
    // ✅ FIXED: Jangan set ke "0,00" kalau user belum input apa-apa
    if (v.trim() === '') {
        // Biarkan kosong, jangan auto-fill
        return;
    }
    
    // ✅ Handle nilai 0 dengan benar
    if (isNaN(num)) {
        this.value = '0';
    } else if (num === 0) {
        this.value = '0';  // Simpan sebagai "0" aja, bukan "0,00"
    } else {
        this.value = num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
});

jumlahInput.addEventListener('focus', function() {
    // ✅ FIXED: Jangan manipulasi kalau value = "0"
    if (this.value === '0' || this.value === '0,00') {
        this.value = '0';
        this.select(); // Auto-select untuk mudah diganti
        return;
    }
    
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
// FORM VALIDATION (FIXED)
// ===================================
document.querySelector('.data-form').addEventListener('submit', function(e) {
    const rawValue = document.getElementById('jumlah_kerugian').value.trim();
    
    // ✅ FIXED: Cek kalau field kosong
    if (rawValue === '') {
        e.preventDefault();
        alert('❌ Jumlah kerugian harus diisi!');
        document.getElementById('jumlah_kerugian').focus();
        return false;
    }
    
    // Parse value
    const raw = rawValue
        .replace(/[^\d,.]/g, '')
        .replace(/\./g, '')
        .replace(/,/g, '.');
    
    const jumlah = parseFloat(raw);
    
    // ✅ FIXED: Cek NaN setelah parsing
    if (isNaN(jumlah)) {
        e.preventDefault();
        alert('❌ Format jumlah kerugian tidak valid!');
        document.getElementById('jumlah_kerugian').focus();
        return false;
    }
    
    // ✅ FIXED: HANYA tolak nilai negatif, terima 0 dan positif
    if (jumlah < 0) {
        e.preventDefault();
        alert('❌ Jumlah kerugian tidak boleh negatif!');
        document.getElementById('jumlah_kerugian').focus();
        return false;
    }
    
    // ✅ Nilai 0 dan positif diperbolehkan
    console.log('✅ Form valid, jumlah kerugian:', jumlah);
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
document.getElementById('tanggal_kerugian').value = new Date().toISOString().split('T')[0];
