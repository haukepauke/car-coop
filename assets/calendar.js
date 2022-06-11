import { Calendar } from "@fullcalendar/core";
import interactionPlugin from "@fullcalendar/interaction";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import listPlugin from "@fullcalendar/list";

import "./styles/calendar.css"; // this will create a calendar.css file reachable to 'encore_entry_link_tags'
document.addEventListener("DOMContentLoaded", () => {
    let calendarEl = document.getElementById("calendar-holder");
  
    let { eventsUrl } = calendarEl.dataset;
  
    let calendar = new Calendar(calendarEl, {
      editable: true,
      eventSources: [
        {
          url: eventsUrl,
          method: "POST",
          extraParams: {
            filters: JSON.stringify({}) // pass your parameters to the subscriber
          },
          failure: () => {
            // alert("There was an error while fetching FullCalendar!");
          },
        },
      ],
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek"
      },
      initialView: "dayGridMonth",
      navLinks: true, // can click day/week names to navigate views
      plugins: [ interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin ],
      timeZone: "UTC",
    });
  
    calendar.render();
});

// document.addEventListener('DOMContentLoaded', () => {
//     var calendarEl = document.getElementById('calendar-holder');

//     var calendar = new FullCalendar.Calendar(calendarEl, {
//         defaultView: 'dayGridMonth',
//         editable: true,
//         eventSources: [
//             {
//                 url: "{{ path('fc_load_events') }}",
//                 method: "POST",
//                 extraParams: {
//                     filters: JSON.stringify({})
//                 },
//                 failure: () => {
//                     // alert("There was an error while fetching FullCalendar!");
//                 },
//             },
//         ],
//         header: {
//             left: 'prev,next today',
//             center: 'title',
//             right: 'dayGridMonth,timeGridWeek,timeGridDay',
//         },
//         plugins: [ 'interaction', 'dayGrid', 'timeGrid' ], // https://fullcalendar.io/docs/plugin-index
//         timeZone: 'UTC',
//     });
//     calendar.render();
// });