        {{-- Función Alpine del autocompletado de dirección. Se incluye tanto en la
             página completa como en el Chat (para que el panel embebido la tenga). --}}
        <script>
            window.direccionAutocomplete = window.direccionAutocomplete || function(apiKey) {
                return {
                    apiKey: apiKey,
                    texto: '',
                    sugerencias: [],
                    abierto: false,
                    sessionToken: null,
                    coordsOk: false,
                    barrioOk: false,
                    init() {
                        // Sincronizar con el valor inicial de Livewire (si lo hay).
                        this.texto = this.$wire.get('direccion') || '';
                        this.$wire.$watch('direccion', (val) => {
                            if ((val || '') !== this.texto) this.texto = val || '';
                        });
                    },
                    nuevoToken() {
                        // Un token UUID por sesión de búsqueda (lo pide Places New).
                        this.sessionToken = (crypto.randomUUID
                            ? crypto.randomUUID()
                            : ('t' + Date.now() + Math.random().toString(16).slice(2)));
                    },
                    async buscar() {
                        // Mantener el campo direccion en Livewire mientras escribe.
                        this.$wire.set('direccion', this.texto, false);

                        const q = (this.texto || '').trim();
                        if (q.length < 3) { this.sugerencias = []; this.abierto = false; return; }
                        if (!this.sessionToken) this.nuevoToken();

                        try {
                            const resp = await fetch('https://places.googleapis.com/v1/places:autocomplete', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Goog-Api-Key': this.apiKey,
                                },
                                body: JSON.stringify({
                                    input: q,
                                    languageCode: 'es',
                                    regionCode: 'CO',
                                    sessionToken: this.sessionToken,
                                }),
                            });
                            const data = await resp.json();
                            if (data.error) { console.warn('Places New error:', data.error.message); this.sugerencias = []; return; }
                            this.sugerencias = (data.suggestions || [])
                                .filter(s => s.placePrediction)
                                .map(s => {
                                    const p = s.placePrediction;
                                    const sf = p.structuredFormat || {};
                                    return {
                                        texto:      p.text?.text || '',                 // completo (lo que se guarda)
                                        principal:  sf.mainText?.text || p.text?.text || '', // calle
                                        secundario: sf.secondaryText?.text || '',        // ciudad/barrio
                                        placeId:    p.placeId,
                                    };
                                });
                            this.abierto = this.sugerencias.length > 0;
                        } catch (e) {
                            console.warn('Places New fetch falló:', e);
                            this.sugerencias = [];
                        }
                    },
                    async elegir(s) {
                        this.texto = s.texto;
                        this.$wire.set('direccion', s.texto);
                        this.abierto = false;
                        this.sugerencias = [];
                        this.coordsOk = false;
                        this.barrioOk = false;

                        // Pedir detalles para extraer el barrio.
                        try {
                            const resp = await fetch(
                                'https://places.googleapis.com/v1/places/' + s.placeId +
                                '?languageCode=es&sessionToken=' + this.sessionToken,
                                { headers: {
                                    'X-Goog-Api-Key': this.apiKey,
                                    'X-Goog-FieldMask': 'addressComponents,formattedAddress,location',
                                } }
                            );
                            const d = await resp.json();
                            let barrio = '';
                            (d.addressComponents || []).forEach(c => {
                                const t = c.types || [];
                                if (t.includes('sublocality') || t.includes('sublocality_level_1') || t.includes('neighborhood')) {
                                    barrio = c.longText || c.shortText || '';
                                }
                            });
                            // 📍 Coordenadas → para que el pedido entre en la ruta.
                            if (d.location && d.location.latitude && d.location.longitude) {
                                this.$wire.set('direccionLat', d.location.latitude, false);
                                this.$wire.set('direccionLng', d.location.longitude, false);
                                this.coordsOk = true;
                            }
                            if (barrio) { this.$wire.set('barrio', barrio); this.barrioOk = true; }
                        } catch (e) { /* place details no disponible — usamos fallback */ }

                        // 📍 RESPALDO vía Text Search (más confiable que Place Details):
                        //    trae coordenadas Y componentes para autocompletar el BARRIO.
                        if (!this.coordsOk || !this.barrioOk) {
                            try {
                                const r2 = await fetch('https://places.googleapis.com/v1/places:searchText', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-Goog-Api-Key': this.apiKey,
                                        'X-Goog-FieldMask': 'places.location,places.addressComponents',
                                    },
                                    body: JSON.stringify({ textQuery: s.texto, languageCode: 'es', regionCode: 'CO' }),
                                });
                                const d2 = await r2.json();
                                const place = d2.places && d2.places[0];
                                const loc = place && place.location;
                                if (!this.coordsOk && loc && loc.latitude && loc.longitude) {
                                    this.$wire.set('direccionLat', loc.latitude, false);
                                    this.$wire.set('direccionLng', loc.longitude, false);
                                    this.coordsOk = true;
                                }
                                if (!this.barrioOk && place) {
                                    let b = '';
                                    (place.addressComponents || []).forEach(c => {
                                        const t = c.types || [];
                                        if (t.includes('sublocality') || t.includes('sublocality_level_1') || t.includes('neighborhood')) {
                                            b = c.longText || c.shortText || '';
                                        }
                                    });
                                    if (b) { this.$wire.set('barrio', b); this.barrioOk = true; }
                                }
                            } catch (e) { console.warn('Text Search falló:', e); }
                        }

                        // 🌐 Respaldo final: si el navegador no logró coords o barrio,
                        //    el SERVIDOR geocodifica la dirección (Google con Referer)
                        //    y llena ambos de forma confiable.
                        if (!this.coordsOk || !this.barrioOk) {
                            try { this.$wire.geocodificarDireccion(s.texto); } catch (e) {}
                        }

                        this.sessionToken = null; // cerrar sesión de búsqueda
                    },
                };
            }

            // 🚚 Calcula el costo de envío geolocalizando la dirección ESCRITA
            //    (venga del ERP, de Google o tecleada). Usa Text Search (New)
            //    para obtener coordenadas y luego pide el cálculo al servidor.
            function envioCalculador(apiKey) {
                return {
                    apiKey: apiKey,
                    cargando: false,
                    async calcular() {
                        const dir = (this.$wire.get('direccion') || '').trim();
                        if (dir.length < 5) {
                            this.$wire.dispatch('notify', { type: 'warning', message: 'Escribe primero la dirección del cliente.' });
                            return;
                        }
                        this.cargando = true;
                        try {
                            const resp = await fetch('https://places.googleapis.com/v1/places:searchText', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Goog-Api-Key': this.apiKey,
                                    'X-Goog-FieldMask': 'places.location,places.formattedAddress',
                                },
                                body: JSON.stringify({ textQuery: dir, languageCode: 'es', regionCode: 'CO' }),
                            });
                            const d = await resp.json();
                            if (d.error) { console.warn('Text Search error:', d.error.message); }
                            const loc = d.places && d.places[0] && d.places[0].location;
                            if (loc && loc.latitude && loc.longitude) {
                                await this.$wire.calcularEnvio(loc.latitude, loc.longitude);
                            } else {
                                this.$wire.dispatch('notify', { type: 'warning', message: 'No pude ubicar esa dirección en el mapa. Ajústala y reintenta, o escribe el costo a mano.' });
                            }
                        } catch (e) {
                            console.warn('envioCalculador falló:', e);
                            this.$wire.dispatch('notify', { type: 'error', message: 'No se pudo calcular el envío. Escribe el costo a mano.' });
                        }
                        this.cargando = false;
                    },
                };
            }

            // 🔎 Select2-like buscable para el campo Barrio.
            function barrioSelect(barrios) {
                return {
                    open: false,
                    barrios: barrios || [],
                    barrio: @entangle('barrio').live,
                    filtrados() {
                        const q = (this.barrio || '').toString().toLowerCase().trim();
                        if (!q) return this.barrios;
                        return this.barrios.filter(b =>
                            (b.nombre || '').toLowerCase().includes(q) ||
                            (b.zona || '').toLowerCase().includes(q)
                        );
                    },
                    elegir(b) {
                        this.barrio = b.nombre;
                        this.open = false;
                    },
                    fmt(n) {
                        try { return new Intl.NumberFormat('es-CO').format(n); } catch (e) { return n; }
                    },
                };
            };
        </script>
