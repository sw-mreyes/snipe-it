// Reservations calendar (custom fork feature).
//
// Mounts a FullCalendar instance on #reservations-calendar and loads events
// from the reservations API endpoint named on the element's data-events-url.
// Clicking an event shows its details (name, reserved-for user, assets, window)
// in #reservation-event-details instead of navigating away. An optional
// data-highlight-id highlights one reservation (e.g. linked from the edit page).

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('reservations-calendar');

    if (!el) {
        return;
    }

    const eventsUrl = el.dataset.eventsUrl;
    const highlightId = el.dataset.highlightId ? parseInt(el.dataset.highlightId, 10) : null;
    const detailsEl = document.getElementById('reservation-event-details');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin],
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek',
        },
        events: function (info, successCallback, failureCallback) {
            // Fetch reservations overlapping the visible window: those that
            // start before the window ends and end after it begins. Passing an
            // explicit range also opts out of the API's upcoming-only default,
            // so past months show their reservations when navigated to.
            const params = new URLSearchParams({
                limit: 1000,
                start_to: info.endStr,
                end_from: info.startStr,
            });

            fetch(eventsUrl + '?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            })
                .then((response) => response.json())
                .then((data) => {
                    const rows = data.rows || [];
                    successCallback(
                        rows.map((row) => ({
                            id: row.id,
                            title: row.name,
                            start: row.start_iso,
                            end: row.end_iso,
                            // Highlight the linked reservation, if any.
                            classNames: highlightId && row.id === highlightId ? ['reservation-highlight'] : [],
                            backgroundColor: highlightId && row.id === highlightId ? '#dd4b39' : undefined,
                            borderColor: highlightId && row.id === highlightId ? '#dd4b39' : undefined,
                            extendedProps: {
                                user: row.user ? row.user.name : null,
                                assets: (row.assets || []).map((a) => (a.name ? a.name : a.asset_tag)),
                                startLabel: row.start && row.start.formatted ? row.start.formatted : row.start_iso,
                                endLabel: row.end && row.end.formatted ? row.end.formatted : row.end_iso,
                                viewUrl: '/reservations/' + row.id,
                            },
                        }))
                    );
                })
                .catch(failureCallback);
        },
        // Show schedule details on click rather than navigating away.
        eventClick: function (info) {
            info.jsEvent.preventDefault();

            if (!detailsEl) {
                window.location = info.event.extendedProps.viewUrl;
                return;
            }

            const props = info.event.extendedProps;
            let html = '<div class="box box-default"><div class="box-body">';
            html += '<h4 style="margin-top:0;"><a href="' + props.viewUrl + '">' + escapeHtml(info.event.title) + '</a></h4>';
            if (props.user) {
                html += '<p><strong>' + escapeHtml(props.user) + '</strong></p>';
            }
            html += '<p>' + escapeHtml(props.startLabel) + ' – ' + escapeHtml(props.endLabel) + '</p>';
            if (props.assets && props.assets.length) {
                html += '<p>' + props.assets.map(escapeHtml).join(', ') + '</p>';
            }
            html += '</div></div>';
            detailsEl.innerHTML = html;
        },
    });

    calendar.render();
});
