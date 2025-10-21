/**
 * SAZEN Investment Portfolio Manager v3.0
 * Form Kerugian - JavaScript Handler
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
document.getElementById('jumlah_kerugian').addEventListener('blur', function() {
    const select = document.getElementById('investasi_id');
    const selected = select.options[select.selectedIndex];
    const pctInput = document.getElementById('persentase_kerugian');
    
    // Skip if user already entered percentage
    if (pctInput.value.trim() !== '') return;
    
    const amount = parseFloat(selected?.dataset.jumlah || 0);
    const lossRaw = this.value.trim();
    
    if (!lossRaw || amount <= 0) return;
    
    // Parse loss (remove dots and replace comma with dot)
    const loss = parseFloat(lossRaw.replace(/\./g, '').replace(',', '.'));
    
    if (loss >= 0) {
        const percentage = (loss / amount) * 100;
        pctInput.value = percentage.toFixed(2);
    }
});


// ===================================
// CURRENCY INPUT FORMATTER
// ===================================
const jumlahInput = document.getElementById('jumlah_kerugian');

jumlahInput.addEventListener('blur', function() {
    let value = this.value.replace(/[^\d]/g, '');
    if (value) {
        this.value = parseInt(value).toLocaleString('id-ID');
    }
});

jumlahInput.addEventListener('focus', function() {
    this.value = this.value.replace(/[^\d]/g, '');
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
    const jumlah = document.getElementById('jumlah_kerugian').value.replace(/[^\d]/g, '');
    
    if (!jumlah || parseInt(jumlah) < 0) {
        e.preventDefault();
        alert('Jumlah kerugian harus diisi dan tidak boleh negatif!');
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
document.getElementById('tanggal_kerugian').value = new Date().toISOString().split('T')[0];