// Reservations calendar (custom fork feature).
//
// Mounts a FullCalendar instance on #reservations-calendar and loads events
// from the reservations API endpoint named on the element's data-events-url.

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('reservations-calendar');

    if (!el) {
        return;
    }

    const eventsUrl = el.dataset.eventsUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

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
                            url: '/reservations/' + row.id,
                        }))
                    );
                })
                .catch(failureCallback);
        },
    });

    calendar.render();
});
