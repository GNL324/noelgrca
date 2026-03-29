// GNL324 — Main JavaScript

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contact-form');
    const status = document.getElementById('form-status');
    const submitBtn = document.getElementById('submit-btn');

    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Collect form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Basic validation
        if (!data.name || !data.email || !data.project_type || !data.message) {
            showStatus('Please fill in all required fields.', 'error');
            return;
        }

        if (!isValidEmail(data.email)) {
            showStatus('Please enter a valid email address.', 'error');
            return;
        }

        // Disable button
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        status.className = 'form-status';
        status.style.display = 'none';

        try {
            const response = await fetch('/api/contact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showStatus('Message sent! We\'ll be in touch within 24 hours.', 'success');
                form.reset();
            } else {
                showStatus(result.error || 'Something went wrong. Please try again.', 'error');
            }
        } catch (err) {
            console.error('Contact form error:', err);
            showStatus('Network error. Please check your connection and try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Message';
        }
    });

    function showStatus(message, type) {
        status.textContent = message;
        status.className = `form-status ${type}`;
        status.style.display = 'block';

        if (type === 'success') {
            setTimeout(() => { status.style.display = 'none'; }, 6000);
        }
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
});
