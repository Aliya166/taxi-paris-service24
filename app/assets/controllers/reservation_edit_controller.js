import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'pickup',
        'dropoff',
        'pickupSuggestions',
        'dropoffSuggestions',
        'pickupLongitude',
        'pickupLatitude',
        'dropoffLongitude',
        'dropoffLatitude'
    ];

    connect() {
        this.pickupTimer = null;
        this.dropoffTimer = null;

        this.pickupRequest = null;
        this.dropoffRequest = null;
    }

    searchPickup() {
        this.clearCoordinates('pickup');

        this.searchAddress(
            this.pickupTarget,
            this.pickupSuggestionsTarget,
            'pickup'
        );
    }

    searchDropoff() {
        this.clearCoordinates('dropoff');

        this.searchAddress(
            this.dropoffTarget,
            this.dropoffSuggestionsTarget,
            'dropoff'
        );
    }

    searchAddress(input, suggestionsBox, type) {
        const query = input.value.trim();

        suggestionsBox.innerHTML = '';

        const timerProperty =
            type === 'pickup'
                ? 'pickupTimer'
                : 'dropoffTimer';

        const requestProperty =
            type === 'pickup'
                ? 'pickupRequest'
                : 'dropoffRequest';

        clearTimeout(this[timerProperty]);

        if (this[requestProperty]) {
            this[requestProperty].abort();
        }

        if (query.length < 4) {
            return;
        }

        this[timerProperty] = setTimeout(async () => {
            const controller = new AbortController();

            this[requestProperty] = controller;

            try {
                const response = await fetch(
                    `/api/address-suggestions?q=${encodeURIComponent(query)}&mode=autocomplete`,
                    {
                        headers: {
                            Accept: 'application/json'
                        },
                        signal: controller.signal
                    }
                );

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const features = data.features || [];

                suggestionsBox.innerHTML = '';

                features.forEach((item) => {
                    const button =
                        document.createElement('button');

                    button.type = 'button';
                    button.className =
                        'reservation-edit-suggestion';

                    button.textContent =
                        item.properties?.label || '';

                    button.addEventListener(
                        'click',
                        () => {
                            this.selectAddress(
                                item,
                                input,
                                suggestionsBox,
                                type
                            );
                        }
                    );

                    suggestionsBox.appendChild(button);
                });
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(
                        'Reservation edit autocomplete error:',
                        error
                    );
                }
            }
        }, 400);
    }

    selectAddress(
        item,
        input,
        suggestionsBox,
        type
    ) {
        const label = item.properties?.label;
        const coordinates = item.geometry?.coordinates;

        if (
            !label
            || !Array.isArray(coordinates)
            || coordinates.length < 2
        ) {
            return;
        }

        input.value = label;
        suggestionsBox.innerHTML = '';

        const longitude = coordinates[0];
        const latitude = coordinates[1];

        if (type === 'pickup') {
            this.pickupLongitudeTarget.value =
                longitude;

            this.pickupLatitudeTarget.value =
                latitude;
        } else {
            this.dropoffLongitudeTarget.value =
                longitude;

            this.dropoffLatitudeTarget.value =
                latitude;
        }
    }

    clearCoordinates(type) {
        if (type === 'pickup') {
            this.pickupLongitudeTarget.value = '';
            this.pickupLatitudeTarget.value = '';
        } else {
            this.dropoffLongitudeTarget.value = '';
            this.dropoffLatitudeTarget.value = '';
        }
    }
}