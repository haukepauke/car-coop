import { Calendar } from 'https://cdn.skypack.dev/@fullcalendar/core@6.1.15';
import interactionPlugin from "https://cdn.skypack.dev/@fullcalendar/interaction@6.1.15";
import dayGridPlugin from "https://cdn.skypack.dev/@fullcalendar/daygrid@6.1.15";
import timeGridPlugin from "https://cdn.skypack.dev/@fullcalendar/timegrid@6.1.15";
import listPlugin from "https://cdn.skypack.dev/@fullcalendar/list@6.1.15";

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