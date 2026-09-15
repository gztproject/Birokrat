import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static values = { url: String };

    bookVisible() {
        const params = new URLSearchParams(window.location.search);
        const dateInput = document.getElementById('modalDate');
        const modal = document.getElementById('dateModal');
        if (dateInput) {
            dateInput.value = new Date().toISOString().slice(0, 10);
        }
        if (modal) {
            Modal.getOrCreateInstance(modal).show();
        }
        document.getElementById('submitDate')?.addEventListener('click', async () => {
            await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({
                    dateFrom: params.get('dateFrom') ?? '',
                    dateTo: params.get('dateTo') ?? '',
                    booked: params.get('booked') ?? '',
                    unbooked: params.get('unbooked') ?? '',
                    date: dateInput?.value ?? '',
                }),
            });
            Modal.getOrCreateInstance(modal).hide();
            window.location.reload();
        }, { once: true });
    }

    toggleButtons() {
        const anyChecked = [...document.querySelectorAll('input.TECheckBox[type="checkbox"]:checked')]
            .some((box) => box.offsetParent !== null);
        const checkedBtn = document.getElementById('bookCheckedTEs');
        const visibleBtn = document.getElementById('bookVisibleTEs');
        if (checkedBtn) {
            checkedBtn.classList.toggle('d-none', !anyChecked);
        }
        if (visibleBtn) {
            visibleBtn.classList.toggle('d-none', anyChecked);
        }
    }
}
