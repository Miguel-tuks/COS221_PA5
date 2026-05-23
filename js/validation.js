

document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-validate-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var ok = true;
            form.querySelectorAll('[data-validate]').forEach(function (input) {
                if (!validateField(input, form)) ok = false;
            });
            if (!ok) e.preventDefault();
        });

        form.querySelectorAll('[data-validate]').forEach(function (input) {
            input.addEventListener('input', function () {
                input.closest('.field').classList.remove('has-error');
            });
        });
    });
});

function validateField(input, form) {
    var rules = input.getAttribute('data-validate').split('|');
    var value = (input.value || '').trim();
    var field = input.closest('.field');
    var errEl = field ? field.querySelector('.error') : null;

    function fail(msg) {
        if (field) field.classList.add('has-error');
        if (errEl) errEl.textContent = msg;
        return false;
    }

    for (var i = 0; i < rules.length; i++) {
        var rule = rules[i];

        if (rule === 'required' && value === '') {
            return fail('This field is required.');
        }
        if (rule === 'email' && value !== '' &&
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return fail('Please enter a valid email address.');
        }
        if (rule === 'number' && value !== '' && isNaN(parseFloat(value))) {
            return fail('Please enter a number.');
        }
        if (rule === 'password' && value !== '' &&
            !(value.length >= 8 && /[A-Za-z]/.test(value) && /\d/.test(value))) {
            return fail('Password must be at least 8 chars and contain a letter and a digit.');
        }
        if (rule.indexOf('match:') === 0) {
            var other = form.querySelector('[name="' + rule.slice(6) + '"]');
            if (other && other.value !== input.value) {
                return fail('Values do not match.');
            }
        }
    }
    return true;
}
