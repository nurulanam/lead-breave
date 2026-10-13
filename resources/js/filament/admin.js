import L from 'leaflet';

/**
 * Cell colours by status. Kept in step with the legend in search/map.blade.php.
 */
const TILE_COLORS = {
    pending: '#475569',
    running: '#8b5cf6',
    done: '#10b981',
    failed: '#ef4444',
};

function searchMap(initial) {
    return {
        map: null,
        layers: null,
        areaKey: undefined,

        init() {
            this.map = L.map(this.$refs.canvas, {
                scrollWheelZoom: false,
                zoomControl: true,
                attributionControl: true,
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(this.map);

            this.layers = L.layerGroup().addTo(this.map);
            this.render(initial);

            // Leaflet measures its container on creation; re-measure once layout settles.
            requestAnimationFrame(() => {
                this.map?.invalidateSize();
                this.areaKey = undefined;
                this.render(initial);
            });
        },

        /**
         * Redraw the area and cells. The view only moves when the area itself
         * changes, so live progress updates never jump the map around.
         */
        render(data) {
            if (! this.map || ! data) {
                return;
            }

            this.layers.clearLayers();

            for (const tile of data.tiles ?? []) {
                const color = TILE_COLORS[tile.status] ?? TILE_COLORS.pending;

                L.rectangle(tile.bounds, {
                    color,
                    weight: 1,
                    opacity: 0.9,
                    fillColor: color,
                    fillOpacity: tile.status === 'pending' ? 0.08 : 0.28,
                    interactive: false,
                }).addTo(this.layers);
            }

            if (data.area) {
                L.rectangle(data.area, {
                    color: '#7c3aed',
                    weight: 2,
                    dashArray: '6 6',
                    fill: false,
                    interactive: false,
                }).addTo(this.layers);

                L.circleMarker([data.center.lat, data.center.lng], {
                    radius: 6,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: '#7c3aed',
                    fillOpacity: 1,
                    interactive: false,
                }).addTo(this.layers);
            }

            const areaKey = JSON.stringify(data.area ?? [data.center, data.zoom]);

            if (areaKey === this.areaKey) {
                return;
            }

            this.areaKey = areaKey;

            if (data.area) {
                this.map.flyToBounds(data.area, { padding: [16, 16], duration: 0.6 });
            } else {
                this.map.setView([data.center.lat, data.center.lng], data.zoom ?? 4);
            }
        },

        destroy() {
            this.map?.remove();
            this.map = null;
        },
    };
}

const register = () => window.Alpine.data('searchMap', searchMap);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}
