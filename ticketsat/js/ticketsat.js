/**
 * TicketSat JS principal v2
 */
(function () {
    'use strict';

    function initPendingBanner() {
        if (typeof CFG_GLPI === 'undefined' || !CFG_GLPI.root_doc) return;
        var url = CFG_GLPI.root_doc + '/plugins/ticketsat/ajax/pending.php';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || data.count === 0) return;
                var first = data.surveys[0];
                var surveyName = first.survey_name || 'Encuesta de satisfacción';
                var ticketInfo = first.ticket_name
                    ? first.ticket_name + ' <span style="opacity:.7">(#' + first.tickets_id + ')</span>'
                    : 'Ticket #' + first.tickets_id;
                var countBadge = data.count > 1
                    ? '<span class="ts-banner-badge">+' + (data.count - 1) + ' más</span>'
                    : '';
                var banner = document.createElement('div');
                banner.className = 'ts-pending-banner';
                banner.innerHTML =
                    '<div class="ts-banner-inner">' +
                        '<div class="ts-banner-icon"><i class="ti ti-star-filled"></i></div>' +
                        '<div class="ts-banner-body">' +
                            '<div class="ts-banner-eyebrow">Encuesta pendiente' + (data.count > 1 ? ' — ' + data.count + ' en total' : '') + '</div>' +
                            '<div class="ts-banner-title">' + surveyName + countBadge + '</div>' +
                            '<div class="ts-banner-sub"><i class="ti ti-ticket" style="margin-right:4px"></i>' + ticketInfo + '</div>' +
                            '<a href="' + CFG_GLPI.root_doc + '/plugins/ticketsat/front/respond.php?token=' + encodeURIComponent(first.token) + '" class="ts-survey-link">Responder ahora &rarr;</a>' +
                        '</div>' +
                        '<button class="ts-close-btn" title="Cerrar"><i class="ti ti-x"></i></button>' +
                    '</div>';
                document.body.appendChild(banner);
                banner.querySelector('.ts-close-btn').addEventListener('click', function () {
                    banner.style.transition = 'opacity .3s, transform .3s';
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(40px)';
                    setTimeout(function () { banner.remove(); }, 320);
                });
            })
            .catch(function () {});
    }

    function initStarLabels() {
        document.querySelectorAll('.ts-stars-group input[type="radio"]').forEach(function (inp) {
            inp.addEventListener('change', function () {
                var lblEl = document.getElementById('starlabel_' + this.name.replace('q_', ''));
                if (!lblEl) return;
                var labels = ['', '1 estrella', '2 estrellas', '3 estrellas', '4 estrellas', '5 estrellas'];
                lblEl.textContent = labels[+this.value] || '';
            });
        });
    }

    function initOptions() {
        document.querySelectorAll('.ts-option-item input').forEach(function (inp) {
            inp.addEventListener('change', function () {
                if (inp.type === 'radio') {
                    document.querySelectorAll('input[name="' + inp.name + '"]').forEach(function (r) {
                        r.closest('.ts-option-item').classList.remove('ts-option-checked');
                    });
                }
                if (inp.checked) inp.closest('.ts-option-item').classList.add('ts-option-checked');
                else inp.closest('.ts-option-item').classList.remove('ts-option-checked');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initPendingBanner(); initStarLabels(); initOptions(); });
    } else {
        initPendingBanner(); initStarLabels(); initOptions();
    }
})();
