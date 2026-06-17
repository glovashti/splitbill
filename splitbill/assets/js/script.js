

document.addEventListener('DOMContentLoaded', function () {
    console.log('SplitBill JS Loaded & Ready');

    
    // 1. Konfirmasi Hapus Data (Confirm Dialog)
    
    // Hapus Bill / Anggota dari form submit
    const deleteForms = document.querySelectorAll('.delete-bill-form, .delete-member-form');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            // Mencegah double submit jika sedang proses kirim
            if (form.getAttribute('data-submitting') === 'true') {
                event.preventDefault();
                return;
            }

            let message = 'Apakah Anda yakin ingin menghapus data ini?';
            if (form.classList.contains('delete-bill-form')) {
                message = 'Apakah Anda yakin ingin menghapus tagihan ini? Semua data anggota dan porsi item terkait akan ikut terhapus secara permanen.';
            } else if (form.classList.contains('delete-member-form')) {
                message = 'Apakah Anda yakin ingin menghapus anggota ini dari tagihan?';
            }

            const confirmDelete = confirm(message);
            if (!confirmDelete) {
                event.preventDefault(); // Batalkan submit jika user memilih 'Batal'
            } else {
                form.setAttribute('data-submitting', 'true');
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
            }
        });
    });

    // Hapus Item dari tabel (Menggunakan Trigger Custom Form)
    const deleteItemTriggers = document.querySelectorAll('.delete-item-trigger');
    let isItemDeleting = false;
    deleteItemTriggers.forEach(button => {
        button.addEventListener('click', function () {
            if (isItemDeleting) return;

            const itemId = this.getAttribute('data-id');
            const confirmDelete = confirm('Apakah Anda yakin ingin menghapus makanan/item ini dari tagihan?');
            
            if (confirmDelete) {
                isItemDeleting = true;
                const deleteForm = document.getElementById('deleteItemForm');
                const inputId = document.getElementById('delete_item_id');
                if (deleteForm && inputId) {
                    inputId.value = itemId;
                    deleteForm.submit();
                }
            }
        });
    });

  
    // 2. Manipulasi DOM: Edit Item Modal Handler
    const editItemButtons = document.querySelectorAll('.edit-item-btn');
    editItemButtons.forEach(button => {
        button.addEventListener('click', function () {
            // Ambil data dari data attributes
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const price = this.getAttribute('data-price');
            const qty = this.getAttribute('data-qty');

            // Inject ke Form Modal (Manipulasi DOM)
            document.getElementById('edit_item_id').value = id;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_price').value = price;
            document.getElementById('edit_qty').value = qty;

            // Tampilkan Modal Edit
            const editModalEl = document.getElementById('editItemModal');
            if (editModalEl) {
                const editModal = bootstrap.Modal.getOrCreateInstance(editModalEl);
                editModal.show();
            }
        });
    });


    // 3. Client-Side Form Validations
    
    // Form Registrasi
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (event) {
            let isValid = true;
            
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            // Reset error states
            resetValidationStyles([usernameInput, emailInput, passwordInput, confirmPasswordInput]);

            // Validasi Username
            if (usernameInput.value.trim().length < 3) {
                showErrorInline(usernameInput, 'Username minimal 3 karakter.');
                isValid = false;
            }

            // Validasi Email (Regex)
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(emailInput.value.trim())) {
                showErrorInline(emailInput, 'Format email tidak valid.');
                isValid = false;
            }

            // Validasi Password
            if (passwordInput.value.length < 6) {
                showErrorInline(passwordInput, 'Password minimal harus 6 karakter.');
                isValid = false;
            }

            // Validasi Cocok Password
            if (passwordInput.value !== confirmPasswordInput.value) {
                showErrorInline(confirmPasswordInput, 'Konfirmasi password tidak cocok.');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault(); // Batalkan submit jika ada error
                event.stopPropagation();
            }
        });
    }

    // Form Login
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            let isValid = true;
            const identityInput = document.getElementById('identity');
            const passwordInput = document.getElementById('password');

            resetValidationStyles([identityInput, passwordInput]);

            if (identityInput.value.trim() === '') {
                showErrorInline(identityInput, 'Username/Email wajib diisi.');
                isValid = false;
            }

            if (passwordInput.value.trim() === '') {
                showErrorInline(passwordInput, 'Password wajib diisi.');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    // Helper functions untuk styling validasi
    function showErrorInline(inputEl, message) {
        inputEl.classList.add('is-invalid');
        const feedbackEl = inputEl.closest('.mb-3, .mb-4').querySelector('.invalid-feedback');
        if (feedbackEl) {
            feedbackEl.textContent = message;
            feedbackEl.style.display = 'block'; // Manipulasi DOM
        }
    }

    function resetValidationStyles(inputs) {
        inputs.forEach(input => {
            if (input) {
                input.classList.remove('is-invalid');
                const feedbackEl = input.closest('.mb-3, .mb-4').querySelector('.invalid-feedback');
                if (feedbackEl) {
                    feedbackEl.style.display = '';
                }
            }
        });
    }

  
    // 4. AddEventListener selain Click & Submit:
    //    Live Rupiah Currency Preview (Event: 'input')
    const priceInput = document.getElementById('price');
    if (priceInput) {
        // Buat elemen preview secara dinamis di bawah input group (DOM Creation)
        const previewEl = document.createElement('div');
        previewEl.id = 'pricePreview';
        previewEl.className = 'text-primary small mt-1 fw-semibold';
        previewEl.textContent = 'Preview: Rp 0';
        priceInput.closest('.mb-3').appendChild(previewEl);

        // Listener event 'input' (Memenuhi kriteria min. 1 event selain click/submit)
        priceInput.addEventListener('input', function () {
            const val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0) {
                previewEl.textContent = 'Preview: ' + formatRupiah(val);
                previewEl.style.opacity = '1';
            } else {
                previewEl.textContent = 'Preview: Rp 0';
            }
        });
    }

    const editPriceInput = document.getElementById('edit_price');
    if (editPriceInput) {
        const editPreviewEl = document.createElement('div');
        editPreviewEl.id = 'editPricePreview';
        editPreviewEl.className = 'text-primary small mt-1 fw-semibold';
        editPreviewEl.textContent = 'Preview: Rp 0';
        editPriceInput.closest('.mb-3').appendChild(editPreviewEl);

        editPriceInput.addEventListener('input', function () {
            const val = parseFloat(this.value);
            if (!isNaN(val) && val >= 0) {
                editPreviewEl.textContent = 'Preview: ' + formatRupiah(val);
            } else {
                editPreviewEl.textContent = 'Preview: Rp 0';
            }
        });
    }

    // Fungsi pembantu format rupiah sisi klien
    function formatRupiah(number) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
    }
});
