import { Calendar } from 'https://cdn.skypack.dev/@fullcalendar/core@6.1.15';
import interactionPlugin from "https://cdn.skypack.dev/@fullcalendar/interaction@6.1.15";
import dayGridPlugin from "https://cdn.skypack.dev/@fullcalendar/daygrid@6.1.15";
import timeGridPlugin from "https://cdn.skypack.dev/@fullcalendar/timegrid@6.1.15";
import listPlugin from "https://cdn.skypack.dev/@fullcalendar/list@6.1.15";
import allLocales from "https://cdn.skypack.dev/@fullcalendar/core@6.1.15/locales-all";

import "./styles/calendar.css"; // this will create a calendar.css file
document.addEventListener("DOMContentLoaded", () => {
    let calendarEl = document.getElementById("calendar-holder");
    if (!calendarEl) {
      return;
    }
  
    let { eventsUrl, locale, prevLabel, nextLabel } = calendarEl.dataset;

    const applyNavigationAccessibility = () => {
      const buttonConfigs = [
        { selector: ".fc-prev-button", label: prevLabel },
        { selector: ".fc-next-button", label: nextLabel },
      ];

      buttonConfigs.forEach(({ selector, label }) => {
        const button = calendarEl.querySelector(selector);
        if (!button || !label) {
          return;
        }

        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);

        button.querySelectorAll(".fc-icon").forEach((icon) => {
          icon.setAttribute("aria-hidden", "true");
          icon.setAttribute("role", "presentation");
        });
      });
    };

    let calendar = new Calendar(calendarEl, {
      locales: allLocales,
      locale: locale,
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
      datesSet: applyNavigationAccessibility,
    });
  
    calendar.render();
    applyNavigationAccessibility();
}); 
