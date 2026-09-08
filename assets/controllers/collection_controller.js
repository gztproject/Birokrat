import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];
    static values = {
        minRows: { type: Number, default: 2 },
        prototypeName: { type: String, default: '__name__' },
    };

    connect() {
        const existing = this.rows().length;
        this.containerTarget.dataset.index = String(existing);
        while (this.rows().length < this.minRowsValue) {
            this.add();
        }
    }

    add() {
        const index = Number(this.containerTarget.dataset.index || 0);
        const prototype = this.containerTarget.dataset.prototype;
        const html = prototype.replace(new RegExp(this.prototypeNameValue, 'g'), String(index));
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html;
        const row = wrapper.querySelector('[data-collection-row]')
            || wrapper.querySelector('tr')
            || wrapper.firstElementChild
            || this.buildTravelStopRow(html, index);
        row.setAttribute('data-collection-row', '');
        this.containerTarget.append(row);
        this.containerTarget.dataset.index = String(index + 1);
        this.renumber();
        this.dispatch('added', { detail: { index, row } });
    }

    buildTravelStopRow(html, index) {
        const doc = new DOMParser().parseFromString(`<table>${html}</table>`, 'text/html');
        const order = doc.getElementById(`travel_expense_travelStopCommands_${index}_stopOrder`);
        const post = doc.getElementById(`travel_expense_travelStopCommands_${index}_post`);
        const distance = doc.getElementById(`travel_expense_travelStopCommands_${index}_distanceFromPrevious`);
        const tr = document.createElement('tr');
        tr.className = `travel-stop-tr-${index}`;
        tr.innerHTML = `<td class="StopOrder">${order ? order.outerHTML : ''}</td>
            <td class="post-Selector">${post ? post.outerHTML : ''}</td>
            <td colspan="2">${distance ? distance.outerHTML : ''}</td>
            <td><button type="button" class="btn btn-sm btn-danger" data-action="collection#remove">−</button></td>`;
        const orderInput = tr.querySelector('[id$="_stopOrder"]');
        if (orderInput) {
            orderInput.value = index + 1;
        }
        return tr;
    }

    remove(event) {
        const rows = this.rows();
        if (rows.length <= this.minRowsValue) {
            window.alert(`Can't delete last ${this.minRowsValue} stops!`);
            return;
        }
        event.currentTarget.closest('[data-collection-row]').remove();
        this.renumber();
    }

    rows() {
        return this.containerTarget.querySelectorAll('[data-collection-row]');
    }

    renumber() {
        this.rows().forEach((row, index) => {
            const order = row.querySelector('[id$="_stopOrder"], .StopOrder input');
            if (order) {
                order.value = index + 1;
            }
        });
    }
}
