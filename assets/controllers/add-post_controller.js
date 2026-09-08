import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    open() {
        const countryName = document.getElementById('country_name')?.innerText;
        const countrySelect = document.getElementById('post_country');
        if (countrySelect && countryName) {
            [...countrySelect.options].forEach((option) => {
                if (option.text === countryName) {
                    option.selected = true;
                }
            });
        }
        Modal.getOrCreateInstance(document.getElementById('addPostModal')).show();
    }

    async create() {
        await fetch('/dashboard/codesheets/post/new', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({
                'post[name]': document.getElementById('post_name')?.value ?? '',
                'post[code]': document.getElementById('post_code')?.value ?? '',
                'post[codeInternational]': document.getElementById('post_codeInternational')?.value ?? '',
                'post[country]': document.getElementById('post_country')?.value ?? '',
                'post[_token]': document.getElementById('post__token')?.value ?? '',
            }),
        });
        Modal.getOrCreateInstance(document.getElementById('addPostModal')).hide();
        window.location.reload();
    }
}
