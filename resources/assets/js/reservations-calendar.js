// Reservations calendar (custom fork feature).
//
// Mounts FullCalendar on #reservations-calendar and loads events from the
// reservations API named on the element's data-events-url. Only the visible
// window is fetched. Clicking an event shows its details in-page rather than
// navigating away; data-highlight-id marks one reservation, e.g. when arriving
// from the detail page.

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';

document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('reservations-calendar');

    if (!el) {
        return;
    }

    const eventsUrl = el.dataset.eventsUrl;
    const locale = el.dataset.locale || 'en';
    const highlightId = el.dataset.highlightId ? parseInt(el.dataset.highlightId, 10) : null;
    const detailsEl = document.getElementById('reservation-event-details');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin],
        initialView: 'dayGridMonth',
        locale: locale,
        height: 'auto',
        firstDay: 1,
        // 24-hour times everywhere. Without this FullCalendar falls back to US
        // 12-hour formatting and prefixes events with e.g. "10a".
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        views: {
            // On the month grid the bar already shows the span, so the start
            // time is just noise in front of every name.
            dayGridMonth: { displayEventTime: false },
        },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth',
        },
        events: function (info, successCallback, failureCallback) {
            // Reservations overlapping the visible window: those starting before
            // it ends and ending after it begins. Passing an explicit range also
            // opts out of the API's upcoming-only default, so past months show
            // their reservations when navigated to.
            const params = new URLSearchParams({
                limit: 1000,
                start_to: info.endStr,
                end_from: info.startStr,
            });

            fetch(eventsUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                credentials: 'same-origin',
            })
                .then((response) => response.json())
                .then((data) => {
                    const rows = data.rows || [];

                    successCallback(
                        rows.map((row) => {
                            const highlighted = highlightId && row.id === highlightId;

                            return {
                                id: row.id,
                                title: row.name,
                                start: row.start_iso,
                                end: row.end_iso,
                                classNames: highlighted ? ['reservation-highlight'] : [],
                                // Status colours match the badges used in the list.
                                backgroundColor: highlighted
                                    ? '#dd4b39'
                                    : row.status === 'active'
                                        ? '#00a65a'
                                        : row.status === 'past'
                                            ? '#d2d6de'
                                            : '#3c8dbc',
                                borderColor: highlighted ? '#dd4b39' : undefined,
                                textColor: row.status === 'past' && !highlighted ? '#444' : undefined,
                                extendedProps: {
                                    user: row.user ? row.user.name : null,
                                    // `label` is resolved server-side (name, else tag, else #id).
                                    assets: (row.assets || []).map((a) => (a.label ? a.label : a.asset_tag)),
                                    startLabel: row.start && row.start.formatted ? row.start.formatted : row.start_iso,
                                    endLabel: row.end && row.end.formatted ? row.end.formatted : row.end_iso,
                                    viewUrl: '/reservations/' + row.id,
                                },
                            };
                        })
                    );
                })
                .catch(failureCallback);
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();

            const props = info.event.extendedProps;

            if (!detailsEl) {
                window.location = props.viewUrl;
                return;
            }

            let html = '<div class="box box-default"><div class="box-body">';
            html += '<h4 style="margin-top:0;"><a href="' + props.viewUrl + '">' + escapeHtml(info.event.title) + '</a></h4>';

            if (props.user) {
                html += '<p><strong>' + escapeHtml(props.user) + '</strong></p>';
            }

            html += '<p>' + escapeHtml(props.startLabel) + ' – ' + escapeHtml(props.endLabel) + '</p>';

            if (props.assets && props.assets.length) {
                html += '<p class="text-muted">' + props.assets.map(escapeHtml).join(', ') + '</p>';
            }

            html += '</div></div>';
            detailsEl.innerHTML = html;
            detailsEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },
    });

    calendar.render();
});
