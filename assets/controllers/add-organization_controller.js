import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    async open() {
        const response = await fetch('/dashboard/organization/list');
        const data = await response.json();
        const select = document.getElementById('AddOrganizationList');
        if (select) {
            select.innerHTML = '';
            data[0].data.organizations.forEach((org) => select.append(new Option(org.name, org.id)));
        }
        Modal.getOrCreateInstance(document.getElementById('addOrganizationModal')).show();
    }

    async add() {
        const response = await fetch('/admin/user/addOrganization', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                userId: document.getElementById('userId')?.value ?? '',
                organizationId: document.getElementById('AddOrganizationList')?.value ?? '',
            }),
        });
        const data = await response.json();
        Modal.getOrCreateInstance(document.getElementById('addOrganizationModal')).hide();
        const list = document.getElementById('organizationList');
        if (list && data?.[0]?.data?.organization) {
            const item = document.createElement('li');
            item.innerHTML = `<a href="/dashboard/organization/${data[0].data.organization.id}"> ${data[0].data.organization.fullAddress}</a>`;
            list.append(item);
        }
    }
}
