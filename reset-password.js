document.addEventListener('DOMContentLoaded', () => {
    const resetForm = document.getElementById('resetForm');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const requirements = document.querySelectorAll('#password-requirements li');
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    // Get token from URL
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');

    if (!token) {
        showMessage('Invalid reset link. Please request a new password reset.', false);
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
        return;
    }

    // Password validation rules
    const passwordRules = {
        minLength: 8,
        hasUpperCase: /[A-Z]/,
        hasLowerCase: /[a-z]/,
        hasNumber: /[0-9]/,
        hasSpecial: /[!@#$%^&*.]/
    };

    function showMessage(message, isSuccess) {
        // Remove any existing messages
        const existingMessages = document.querySelectorAll('.success-message, .error-message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message element
        const messageElement = document.createElement('div');
        messageElement.className = isSuccess ? 'success-message' : 'error-message';
        messageElement.textContent = message;

        // Insert message before the form
        resetForm.insertBefore(messageElement, resetForm.firstChild);

        if (isSuccess) {
            // Disable form inputs on success
            const inputs = resetForm.querySelectorAll('input, button');
            inputs.forEach(input => input.disabled = true);
        }

        // Remove message after 5 seconds if it's an error
        if (!isSuccess) {
            setTimeout(() => messageElement.remove(), 5000);
        }
    }

    function toggleLoading(show) {
        loadingOverlay.style.display = show ? 'flex' : 'none';
        if (show) {
            setTimeout(() => {
                loadingOverlay.style.opacity = '1';
            }, 50);
        } else {
            loadingOverlay.style.opacity = '0';
        }

        // Disable/enable form inputs
        const inputs = resetForm.querySelectorAll('input, button');
        inputs.forEach(input => input.disabled = show);
    }

    function validatePassword(password) {
        const validations = {
            length: password.length >= passwordRules.minLength,
            upperCase: passwordRules.hasUpperCase.test(password),
            lowerCase: passwordRules.hasLowerCase.test(password),
            number: passwordRules.hasNumber.test(password),
            special: passwordRules.hasSpecial.test(password)
        };

        // Update requirement checks in UI with animation
        requirements[0].className = validations.length ? 'check-passed' : 'check-failed';
        requirements[1].className = validations.upperCase ? 'check-passed' : 'check-failed';
        requirements[2].className = validations.number ? 'check-passed' : 'check-failed';
        requirements[3].className = validations.special ? 'check-passed' : 'check-failed';

        return Object.values(validations).every(Boolean);
    }

    passwordInput.addEventListener('input', () => {
        validatePassword(passwordInput.value);
        
        // Check password match if confirm password has a value
        if (confirmPasswordInput.value) {
            const isMatch = confirmPasswordInput.value === passwordInput.value;
            confirmPasswordInput.style.borderColor = isMatch ? '#4CAF50' : '#ff3333';
        }
    });

    confirmPasswordInput.addEventListener('input', () => {
        const isMatch = confirmPasswordInput.value === passwordInput.value;
        confirmPasswordInput.style.borderColor = isMatch ? '#4CAF50' : '#ff3333';
    });

    resetForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (!validatePassword(password)) {
            showMessage('Please ensure your password meets all requirements.', false);
            passwordInput.focus();
            return;
        }

        if (password !== confirmPassword) {
            showMessage('Passwords do not match!', false);
            confirmPasswordInput.focus();
            return;
        }

        try {            toggleLoading(true);
            const API_URL = window.location.hostname === 'localhost' 
                ? 'http://scentsation_api.local/auth/reset_password.php'
                : 'https://apiscentsation.great-site.net/auth/reset_password.php';
            
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    token, 
                    password
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showMessage('Your password has been successfully reset!', true);
                setTimeout(() => {
                    window.location.href = 'https://scentsation.great-site.net/frontend/login.html';
                }, 2000);
            } else {
                showMessage(data.message || 'Failed to reset password. Please try again.', false);
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('An error occurred while resetting your password. Please try again.', false);
        } finally {
            toggleLoading(false);
        }
    });
});