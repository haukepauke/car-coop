document.addEventListener('DOMContentLoaded', function () {
    var container = document.getElementById('invite-entries');
    if (!container) return;

    var singleGroup = container.dataset.singleGroup === '1';
    var entryClass = container.dataset.entryClass || 'invite-entry border rounded p-3 mb-2';
    var index = container.querySelectorAll('.invite-entry').length;

    document.getElementById('add-invite-entry').addEventListener('click', function () {
        var prototype = container.dataset.prototype;
        var html = prototype.replace(/__name__/g, index);

        var wrapper = document.createElement('div');
        wrapper.innerHTML = html;

        var entry = document.createElement('div');
        entry.className = entryClass;

        var emailDiv = document.createElement('div');
        emailDiv.className = 'mb-2';
        var emailLabel = wrapper.querySelector('label');
        var emailInput = wrapper.querySelector('input[type="email"], input[type="text"]');
        if (emailInput) { emailInput.className = 'form-control'; }
        if (emailLabel) emailDiv.appendChild(emailLabel);
        if (emailInput) emailDiv.appendChild(emailInput);
        entry.appendChild(emailDiv);

        var selects = wrapper.querySelectorAll('select');
        var labels = wrapper.querySelectorAll('label');
        var userTypeSelect = selects[0];
        var localeSelect = selects[1];

        if (userTypeSelect && !singleGroup) {
            var typeDiv = document.createElement('div');
            typeDiv.className = 'mb-1';
            userTypeSelect.className = 'form-select';
            if (labels[1]) typeDiv.appendChild(labels[1]);
            typeDiv.appendChild(userTypeSelect);
            entry.appendChild(typeDiv);
        } else if (userTypeSelect) {
            userTypeSelect.style.display = 'none';
            entry.appendChild(userTypeSelect);
        }

        if (localeSelect) {
            var localeDiv = document.createElement('div');
            localeDiv.className = 'mb-1';
            localeSelect.className = 'form-select form-select-sm';
            var localeLabel = singleGroup ? labels[1] : labels[2];
            if (localeLabel) localeDiv.appendChild(localeLabel);
            localeDiv.appendChild(localeSelect);
            entry.appendChild(localeDiv);
        }

        container.appendChild(entry);
        index++;
    });
});
