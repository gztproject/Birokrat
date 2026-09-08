import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    open() {
        Modal.getOrCreateInstance(document.getElementById('addAddressModal')).show();
    }

    async create() {
        const response = await fetch('/dashboard/address/new', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                'address[line1]': document.getElementById('address_line1')?.value ?? '',
                'address[line2]': document.getElementById('address_line2')?.value ?? '',
                'address[post]': document.getElementById('address_post')?.value ?? '',
                'address[_token]': document.getElementById('address__token')?.value ?? '',
            }),
        });
        const data = await response.json();
        Modal.getOrCreateInstance(document.getElementById('addAddressModal')).hide();
        const select = document.getElementById('clientAddress');
        if (select && data?.[0]?.data?.address) {
            select.append(new Option(data[0].data.address.fullAddress, data[0].data.address.id));
        }
    }
}
