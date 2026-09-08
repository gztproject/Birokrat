import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];

    async fillDistance(event) {
        const row = event.currentTarget.closest('tr');
        const rows = [...this.containerTarget.querySelectorAll('tr[data-collection-row]')];
        const index = rows.indexOf(row);
        if (index < 1) {
            return;
        }
        const originCity = rows[index - 1].querySelector('select')?.selectedOptions[0]?.text;
        const destinationCity = rows[index].querySelector('select')?.selectedOptions[0]?.text;
        if (!originCity || !destinationCity) {
            return;
        }
        const distance = await this.lookupDistance(originCity, destinationCity);
        const distanceInput = rows[index].querySelector('[id$="_distanceFromPrevious"]');
        if (distanceInput && distance != null) {
            distanceInput.value = distance / 1000;
        }
    }

    async lookupDistance(originCity, destinationCity) {
        const origin = await this.geocode(originCity);
        const destination = await this.geocode(destinationCity);
        if (!origin || !destination) {
            return null;
        }
        const host = process.env.OSRM_HOST;
        if (!host) {
            return null;
        }
        const url = `${host}/table/v1/driving/${origin.lon},${origin.lat};${destination.lon},${destination.lat}?sources=0&destinations=1&annotations=distance`;
        const response = await fetch(url);
        const payload = await response.json();
        if (payload.code !== 'Ok') {
            return null;
        }
        return payload.distances[0][0];
    }

    async geocode(city) {
        const url = `https://nominatim.openstreetmap.org/search/?format=json&country=Slovenija&city=${encodeURIComponent(city)}`;
        const response = await fetch(url);
        const payload = await response.json();
        if (!payload[0]) {
            return null;
        }
        return { lon: payload[0].lon, lat: payload[0].lat };
    }
}
