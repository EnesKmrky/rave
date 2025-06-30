document.getElementById('register-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('username', document.getElementById('reg-username').value);
    formData.append('key', document.getElementById('reg-key').value);
    formData.append('password', document.getElementById('reg-password').value);

    fetch('register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('register-message');
        messageDiv.textContent = data.message;
        if (data.status === 'success') {
            messageDiv.style.color = 'lightgreen';
        } else {
            messageDiv.style.color = 'salmon';
        }
    });
});

document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('username', document.getElementById('login-username').value);
    formData.append('password', document.getElementById('login-password').value);

    fetch('login.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const messageDiv = document.getElementById('login-message');
        messageDiv.textContent = data.message;
         if (data.status === 'success') {
            messageDiv.style.color = 'lightgreen';
            // Giriş başarılıysa ana panele yönlendir
            window.location.href = '/rave/dashboard.php'; // veya panelinizin ana sayfası
        } else {
            messageDiv.style.color = 'salmon';
        }
    });
});