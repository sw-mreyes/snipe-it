// Reservation create/edit form (custom fork feature).
//
// Upgrades the start/end datetime fields to a flatpickr picker so a time can
// be selected (not just typed), shown in 24-hour German format. allowInput
// keeps the fields freely typeable for anyone who prefers the keyboard.

import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';

document.addEventListener('DOMContentLoaded', function () {
    const options = {
        enableTime: true,
        time_24hr: true,
        allowInput: true,
        dateFormat: 'Y-m-d H:i',
        locale: German,
    };

    ['#start', '#end'].forEach((selector) => {
        const el = document.querySelector(selector);
        if (el) {
            flatpickr(el, options);
        }
    });
});
