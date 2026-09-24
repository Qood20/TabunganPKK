// Interaksi global UI: konfirmasi penghapusan dan toggle sidebar mobile.

document.addEventListener('DOMContentLoaded', function() {
    // Tahan submit jika pengguna membatalkan konfirmasi penghapusan.
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Aktifkan menu sidebar mobile jika tombol toggle tersedia pada layout.
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
});
