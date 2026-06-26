// Reservation create/edit form (custom fork feature).
//
// 1. Upgrades the start/end datetime fields to a flatpickr picker so a time can
//    be selected (not just typed), shown in 24-hour German format. allowInput
//    keeps the fields freely typeable for anyone who prefers the keyboard.
// 2. Shows the existing reservations for the selected assets and flags any whose
//    window overlaps the entered start/end (mirrors Reservation::conflictsExist).

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

    initConflictChecker();
});

function initConflictChecker() {
    const container = document.getElementById('reservation-conflicts');
    const assetsSelect = document.getElementById('assets');
    const startEl = document.getElementById('start');
    const endEl = document.getElementById('end');

    if (!container || !assetsSelect) {
        return;
    }

    const template = container.dataset.forassetTemplate;
    const currentId = container.dataset.reservationId
        ? parseInt(container.dataset.reservationId, 10)
        : null;
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    // Two windows overlap iff start1 <= end2 && start2 <= end1.
    function overlaps(aStart, aEnd, bStart, bEnd) {
        return aStart <= bEnd && bStart <= aEnd;
    }

    function selectedAssetIds() {
        return Array.from(assetsSelect.selectedOptions).map((o) => o.value);
    }

    function render(reservations) {
        if (!reservations.length) {
            container.innerHTML =
                '<p class="text-muted">' + container.dataset.none + '</p>';
            return;
        }

        const enteredStart = startEl && startEl.value ? new Date(startEl.value.replace(' ', 'T')) : null;
        const enteredEnd = endEl && endEl.value ? new Date(endEl.value.replace(' ', 'T')) : null;

        let html = '<label class="control-label">' + container.dataset.heading + '</label>';
        html += '<ul class="list-unstyled" style="margin-top:5px;">';

        reservations.forEach((r) => {
            let flagged = false;
            if (enteredStart && enteredEnd && r.start_iso && r.end_iso) {
                flagged = overlaps(
                    enteredStart,
                    enteredEnd,
                    new Date(r.start_iso),
                    new Date(r.end_iso)
                );
            }

            const window = (r.start && r.start.formatted ? r.start.formatted : '') +
                ' – ' +
                (r.end && r.end.formatted ? r.end.formatted : '');

            html += '<li class="' + (flagged ? 'text-danger text-bold' : 'text-muted') + '">';
            if (flagged) {
                html += '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ';
            }
            html += escapeHtml(r.name) + ' (' + window + ')';
            if (flagged) {
                html += ' — ' + container.dataset.overlap;
            }
            html += '</li>';
        });

        html += '</ul>';
        container.innerHTML = html;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    function refresh() {
        const ids = selectedAssetIds();
        if (!template || !ids.length) {
            container.innerHTML = '';
            return;
        }

        Promise.all(
            ids.map((id) =>
                fetch(template.replace('__ASSET_ID__', encodeURIComponent(id)), {
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                })
                    .then((res) => res.json())
                    .then((data) => data.rows || [])
                    .catch(() => [])
            )
        ).then((lists) => {
            // Merge, drop the reservation being edited, and de-duplicate by id.
            const byId = {};
            lists.flat().forEach((r) => {
                if (currentId && r.id === currentId) {
                    return;
                }
                byId[r.id] = r;
            });
            render(Object.values(byId));
        });
    }

    // select2 fires a native 'change' on the underlying <select>.
    assetsSelect.addEventListener('change', refresh);
    [startEl, endEl].forEach((el) => {
        if (el) {
            el.addEventListener('change', refresh);
        }
    });

    refresh();
}
