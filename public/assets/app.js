/* e-HadirGo — maps, geolocation, offline queue, service worker. */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    function haversine(lat1, lng1, lat2, lng2) {
        var R = 6371000, rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return Math.round(2 * R * Math.asin(Math.min(1, Math.sqrt(a))));
    }

    function formatDistance(meters) {
        var m = Math.max(0, Math.round(meters));
        return m < 1000 ? m + ' m' : (m / 1000).toFixed(m < 10000 ? 1 : 0) + ' km';
    }

    function locate() {
        return new Promise(function (resolve, reject) {
            if (!('geolocation' in navigator)) {
                reject(new Error('unsupported'));
                return;
            }
            if (typeof navigator.geolocation.watchPosition !== 'function') {
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    timeout: 20000,
                    maximumAge: 0
                });
                return;
            }

            var bestPosition = null;
            var watchId = null;
            var settled = false;
            var timer = null;

            function finish(error) {
                if (settled) return;
                settled = true;
                if (timer !== null) window.clearTimeout(timer);
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                if (bestPosition) {
                    resolve(bestPosition);
                } else {
                    reject(error || new Error('unavailable'));
                }
            }

            watchId = navigator.geolocation.watchPosition(function (position) {
                var accuracy = Number(position.coords.accuracy);
                if (!bestPosition || accuracy < bestPosition.coords.accuracy) {
                    bestPosition = position;
                }
                if (accuracy <= 20) finish();
            }, function (error) {
                if (error.code === 1) finish(error);
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            });

            if (settled) {
                if (watchId !== null) navigator.geolocation.clearWatch(watchId);
            } else {
                timer = window.setTimeout(function () {
                    finish(bestPosition ? null : new Error('timeout'));
                }, 10000);
            }
        });
    }

    var updateAssignmentMapLocation = null;
    var TILE = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    var ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

    function baseMap(el, lat, lng, zoom) {
        var map = L.map(el, { zoomControl: true, scrollWheelZoom: false }).setView([lat, lng], zoom || 15);
        L.tileLayer(TILE, { maxZoom: 19, attribution: ATTR }).addTo(map);
        return map;
    }

    /* ---------------------------------------------------------- view map */
    function initViewMap() {
        var el = $('#map');
        if (!el || !window.L) return;

        var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
        var radius = parseInt(el.dataset.radius, 10) || 250;
        var map = baseMap(el, lat, lng, 15);

        L.circle([lat, lng], { radius: radius, color: '#2e75b6', weight: 2, fillColor: '#2e75b6', fillOpacity: 0.12 }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup(el.dataset.label || '');
        map.fitBounds([[lat, lng]], { maxZoom: 16, padding: [40, 40] });

        var checkinForm = $('#checkin-form');
        var hereMarker = null;
        var accuracyCircle = null;
        var distanceLine = null;
        updateAssignmentMapLocation = function (userLat, userLng, accuracy) {
            var here = [userLat, userLng];
            var distance = haversine(userLat, userLng, lat, lng);
            if (!hereMarker) {
                hereMarker = L.circleMarker(here, {
                    radius: 7, color: '#fff', weight: 2, fillColor: '#1e7f4f', fillOpacity: 1
                }).addTo(map).bindPopup('Anda di sini');
            } else {
                hereMarker.setLatLng(here);
            }

            if (accuracy > 0) {
                if (!accuracyCircle) {
                    accuracyCircle = L.circle(here, {
                        radius: accuracy, color: '#526579', weight: 1, fillColor: '#526579', fillOpacity: 0.12
                    }).addTo(map);
                } else {
                    accuracyCircle.setLatLng(here);
                    accuracyCircle.setRadius(accuracy);
                }
            } else if (accuracyCircle) {
                map.removeLayer(accuracyCircle);
                accuracyCircle = null;
            }

            if (checkinForm && distance > radius) {
                if (!distanceLine) {
                    distanceLine = L.polyline([here, [lat, lng]], {
                        color: '#a8281f', weight: 3, dashArray: '8 8'
                    }).addTo(map);
                } else {
                    distanceLine.setLatLngs([here, [lat, lng]]);
                }
            } else if (distanceLine) {
                map.removeLayer(distanceLine);
                distanceLine = null;
            }

            map.fitBounds([here, [lat, lng]], { padding: [40, 40], maxZoom: 16 });
        };

        if (!checkinForm) {
            locate().then(function (pos) {
                updateAssignmentMapLocation(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
            }).catch(function () { /* location optional on the view page */ });
        }
    }

    /* ------------------------------------------------------- picker map */
    function initPicker() {
        var el = $('#picker');
        if (!el) return;

        var latInput = $('#lat'), lngInput = $('#lng');
        var nameInput = $('#location_name'), radiusSelect = $('#radius_m');
        var info = $('#picker-info');
        var saved = $('#saved-location');
        var search = $('#place-search');
        var queryInput = $('#place-query');
        var searchButton = $('#place-search-btn');
        var results = $('#place-results');
        var fallback = $('#google-place-fallback');
        var googleTitle = $('#google-place-title');
        var googleHelp = $('#google-place-help');
        var googleLink = $('#google-maps-link');
        var googlePasteLabel = $('#google-place-paste-label');
        var googlePaste = $('#google-place-value');
        var googleConfirm = $('#google-place-confirm');
        var googleStatus = $('#google-place-status');
        var searchInProgress = false;
        var lastSearchAt = 0;

        var lat = parseFloat(el.dataset.lat) || 5.9804;
        var lng = parseFloat(el.dataset.lng) || 116.0735;
        var radius = parseInt(el.dataset.radius, 10) || 250;

        var map = null, marker = null, circle = null;
        if (window.L) {
            map = baseMap(el, lat, lng, 13);
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            circle = L.circle([lat, lng], {
                radius: radius, color: '#2e75b6', weight: 2, fillColor: '#2e75b6', fillOpacity: 0.12
            }).addTo(map);
        } else {
            el.classList.add('map-unavailable');
            el.textContent = el.dataset.unavailable || '';
        }

        function setPoint(la, ln, zoom, label) {
            if (marker && circle) {
                marker.setLatLng([la, ln]);
                circle.setLatLng([la, ln]);
            }
            if (latInput) latInput.value = la.toFixed(6);
            if (lngInput) lngInput.value = ln.toFixed(6);
            if (info) info.textContent = (label ? label + ' · ' : '') + la.toFixed(6) + ', ' + ln.toFixed(6);
            if (zoom && map) {
                // The map can be laid out after its first render; resizing it
                // first is what makes a searched place actually appear centred.
                map.invalidateSize();
                map.setView([la, ln], zoom);
            }
            if (label && marker) {
                var popup = document.createElement('span');
                popup.textContent = label;
                marker.bindPopup(popup).openPopup();
            }
        }

        function parseCoordinatePair(value) {
            var match = value.match(/^\s*\(?\s*(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*\)?\s*$/);
            if (!match) return null;

            var latitude = Number(match[1]), longitude = Number(match[2]);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)
                || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return null;

            return { lat: latitude, lng: longitude };
        }

        function parseGoogleMapsCoordinates(value) {
            var text = value.trim();
            var direct = parseCoordinatePair(text);
            if (direct) return direct;

            try {
                var url = new URL(text);
                var host = url.hostname.toLowerCase();
                if (!/^https?:$/.test(url.protocol)
                    || !(host === 'google.com' || /(^|\.)google\.[a-z.]+$/.test(host)
                        || host === 'goo.gl' || host.endsWith('.goo.gl'))) return null;

                var point = text.match(/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/i)
                    || text.match(/@(-?\d{1,2}(?:\.\d+)?),\s*(-?\d{1,3}(?:\.\d+)?)/);
                if (point) return parseCoordinatePair(point[1] + ',' + point[2]);

                var params = url.searchParams;
                var query = params.get('query') || params.get('q') || params.get('ll') || params.get('destination');
                return query ? parseCoordinatePair(query) : null;
            } catch (error) {
                return null;
            }
        }

        function showGoogleFallback(query) {
            if (!fallback || !search) return;
            fallback.classList.remove('hidden');
            if (googleTitle) googleTitle.textContent = search.dataset.googleTitle || '';
            if (googleHelp) googleHelp.textContent = search.dataset.googleHelp || '';
            if (googleLink) {
                googleLink.textContent = search.dataset.googleOpen || '';
                googleLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(query);
            }
            if (googlePasteLabel) googlePasteLabel.textContent = search.dataset.googlePaste || '';
            if (googlePaste) googlePaste.placeholder = search.dataset.googlePlaceholder || '';
            if (googleConfirm) googleConfirm.textContent = search.dataset.googleConfirm || '';
            if (googleStatus) {
                googleStatus.textContent = '';
                googleStatus.classList.add('hidden');
                googleStatus.classList.remove('bad', 'ok');
            }
        }

        function searchPlaces() {
            if (!search || !queryInput || !searchButton || !results || searchInProgress) return;

            var query = queryInput.value.trim();
            if (query.length < 2) {
                results.textContent = search.dataset.tooShort || '';
                results.classList.remove('hidden');
                return;
            }

            searchInProgress = true;
            searchButton.disabled = true;
            results.textContent = search.dataset.searching || '';
            results.classList.remove('hidden');
            if (fallback) fallback.classList.add('hidden');

            var delay = Math.max(0, 1100 - (Date.now() - lastSearchAt));
            window.setTimeout(function () {
                lastSearchAt = Date.now();
                // Send the visible centre so nearby matches rank first, the same
                // way the Google Maps search box behaves.
                var center = map ? map.getCenter() : { lat: lat, lng: lng };
                var requestUrl = search.dataset.endpoint + '&q=' + encodeURIComponent(query)
                    + '&lat=' + center.lat.toFixed(6) + '&lng=' + center.lng.toFixed(6);
                fetch(requestUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Place search request failed');
                        return response.json();
                    })
                    .then(function (places) {
                        results.textContent = '';
                        if (!Array.isArray(places) || places.length === 0) {
                            results.textContent = search.dataset.empty || '';
                            showGoogleFallback(query);
                            return;
                        }

                        places.forEach(function (place) {
                            var la = Number(place.lat), ln = Number(place.lon);
                            if (!Number.isFinite(la) || !Number.isFinite(ln) || la < -90 || la > 90 || ln < -180 || ln > 180) return;

                            var button = document.createElement('button');
                            var title = document.createElement('strong');
                            var address = document.createElement('span');
                            var label = String(place.name || place.display_name || '').trim();
                            var meta = String(place.display_name || '');
                            var distance = Number(place.distance_m);
                            if (Number.isFinite(distance)) {
                                meta += (meta ? ' · ' : '') + formatDistance(distance);
                            }

                            button.type = 'button';
                            button.className = 'place-result';
                            title.textContent = label;
                            address.textContent = meta;
                            button.appendChild(title);
                            button.appendChild(address);
                            button.addEventListener('click', function () {
                                if (nameInput) nameInput.value = label.slice(0, 150);
                                setPoint(la, ln, 16, label);
                                results.classList.add('hidden');
                            });
                            results.appendChild(button);
                        });

                        if (!results.firstChild) {
                            results.textContent = search.dataset.empty || '';
                            showGoogleFallback(query);
                        }
                    })
                    .catch(function () {
                        results.textContent = search.dataset.error || '';
                        showGoogleFallback(query);
                    })
                    .finally(function () {
                        searchInProgress = false;
                        searchButton.disabled = false;
                    });
            }, delay);
        }

        if (googleConfirm && googlePaste) {
            googleConfirm.addEventListener('click', function () {
                var point = parseGoogleMapsCoordinates(googlePaste.value);
                if (!point) {
                    if (googleStatus) {
                        googleStatus.textContent = search.dataset.googleInvalid || '';
                        googleStatus.classList.remove('hidden', 'ok');
                        googleStatus.classList.add('bad');
                    }
                    return;
                }

                var label = nameInput && nameInput.value.trim()
                    ? nameInput.value.trim()
                    : queryInput.value.trim();
                if (nameInput && !nameInput.value.trim()) nameInput.value = label.slice(0, 150);
                setPoint(point.lat, point.lng, 16, label);
                if (googleStatus) {
                    googleStatus.textContent = search.dataset.googleApplied || '';
                    googleStatus.classList.remove('hidden', 'bad');
                    googleStatus.classList.add('ok');
                }
            });
        }

        function setRadius(r) {
            radius = r;
            if (circle) circle.setRadius(r);
        }

        setPoint(lat, lng, 0);

        if (map && marker) {
            map.on('click', function (e) {
                setPoint(e.latlng.lat, e.latlng.lng, 0);
            });

            marker.on('dragend', function () {
                var p = marker.getLatLng();
                setPoint(p.lat, p.lng, 0);
            });
        }

        if (radiusSelect) {
            radiusSelect.addEventListener('change', function () {
                setRadius(parseInt(radiusSelect.value, 10) || 250);
            });
        }

        if (searchButton && queryInput) {
            searchButton.addEventListener('click', searchPlaces);
            queryInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchPlaces();
                }
            });
        }

        if (saved) {
            saved.addEventListener('change', function () {
                var opt = saved.options[saved.selectedIndex];
                if (!opt || !opt.dataset.lat) return;
                var la = parseFloat(opt.dataset.lat), ln = parseFloat(opt.dataset.lng);
                setPoint(la, ln, 15);
                setRadius(parseInt(opt.dataset.radius, 10) || 250);
                if (nameInput && !nameInput.value) nameInput.value = opt.dataset.name || '';
                if (radiusSelect) radiusSelect.value = String(parseInt(opt.dataset.radius, 10) || 250);
            });
        }

        var useBtn = $('#use-location');
        if (useBtn) {
            useBtn.addEventListener('click', function () {
                useBtn.disabled = true;
                locate().then(function (pos) {
                    setPoint(pos.coords.latitude, pos.coords.longitude, 16);
                }).catch(function () {
                    if (info) info.textContent = 'Lokasi tidak tersedia — tetapkan pin secara manual.';
                }).finally(function () { useBtn.disabled = false; });
            });
        }
    }

    /* ----------------------------------------------- assignment participants */
    function initParticipantsPicker() {
        var picker = $('#participants-picker');
        var dialog = $('#participants-dialog');
        if (!picker || !dialog) return;

        var opener = $('#participants-open');
        var cancel = $('#participants-cancel');
        var confirm = $('#participants-confirm');
        var search = $('#participants-search');
        var list = $('#participant-list');
        var noMatch = $('#participants-no-match');
        var summary = $('#participants-summary');
        var values = $('#participants-values');
        var confirmed = $('#participants-confirmed');
        var checkboxes = $$('.participant-option input[type="checkbox"]', list);

        function filterParticipants() {
            var query = (search.value || '').trim().toLocaleLowerCase();
            var visible = 0;
            $$('.participant-option', list).forEach(function (option) {
                var matches = option.textContent.toLocaleLowerCase().indexOf(query) !== -1;
                option.classList.toggle('hidden', !matches);
                if (matches) visible++;
            });
            noMatch.classList.toggle('hidden', visible > 0);
        }

        function confirmParticipants() {
            var selected = checkboxes.filter(function (checkbox) { return checkbox.checked; });
            if (selected.length === 0) {
                summary.textContent = picker.dataset.needed || picker.dataset.empty || '';
                confirmed.value = '0';
                return;
            }

            values.textContent = '';
            selected.forEach(function (checkbox) {
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'staff_ids[]';
                hidden.value = checkbox.value;
                values.appendChild(hidden);
            });
            summary.textContent = (picker.dataset.confirmed || '') + ' ' + selected.map(function (checkbox) {
                return checkbox.closest('.participant-option').querySelector('strong').textContent;
            }).join(', ');
            confirmed.value = '1';
            closeDialog();
        }

        function openDialog() {
            dialog.classList.remove('hidden');
            dialog.setAttribute('aria-hidden', 'false');
            document.body.classList.add('dialog-open');
            if (search) search.focus();
        }

        function closeDialog() {
            dialog.classList.add('hidden');
            dialog.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('dialog-open');
            if (opener) opener.focus();
        }

        if (opener) opener.addEventListener('click', function () {
            openDialog();
        });
        if (cancel) cancel.addEventListener('click', closeDialog);
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) closeDialog();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dialog.classList.contains('hidden')) closeDialog();
        });
        if (confirm) confirm.addEventListener('click', confirmParticipants);
        if (search) search.addEventListener('input', filterParticipants);

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                if (confirmed.value === '1') {
                    confirmed.value = '0';
                    summary.textContent = picker.dataset.needed || '';
                }
            });
        });

        var form = picker.closest('form');
        if (form) {
            form.addEventListener('submit', function (event) {
                if (confirmed.value !== '1' || values.querySelectorAll('input[name="staff_ids[]"]').length === 0) {
                    event.preventDefault();
                    summary.textContent = picker.dataset.needed || picker.dataset.empty || '';
                    openDialog();
                }
            });
        }
    }

    /* -------------------------------------------------------- live map */
    function initLiveMap() {
        var el = $('#livemap');
        if (!el || !window.L || !window.EHADIR || !window.EHADIR.liveMap) return;

        var rows = window.EHADIR.liveMap;
        var map = baseMap(el, 5.9804, 116.0735, 9);
        var colors = { ok: '#1e7f4f', late: '#d98b00', outside: '#a8281f', pending: '#64708a' };
        var bounds = [];

        rows.forEach(function (r) {
            var color = colors[r.state] || '#64708a';
            L.circle([r.lat, r.lng], {
                radius: r.radius, color: color, weight: 1.5, fillColor: color, fillOpacity: 0.08
            }).addTo(map);
            L.circleMarker([r.lat, r.lng], {
                radius: 7, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1
            }).addTo(map).bindPopup('<strong>' + r.name + '</strong><br>' + r.place + '<br>' + r.time);
            bounds.push([r.lat, r.lng]);
        });

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
        }
    }

    /* ---------------------------------------------------------- check-in */
    function initCheckin() {
        var form = $('#checkin-form');
        if (!form) return;

        var status = $('#geo-status');
        var warning = $('#geo-warning');
        var latIn = $('#in-lat'), lngIn = $('#in-lng'), accIn = $('#in-accuracy');
        var radius = parseFloat(form.dataset.radius) || 250;
        var targetLat = parseFloat(form.dataset.targetLat);
        var targetLng = parseFloat(form.dataset.targetLng);

        function paint(pos) {
            var la = pos.coords.latitude, ln = pos.coords.longitude;
            var acc = Math.round(pos.coords.accuracy || 0);
            var dist = haversine(la, ln, targetLat, targetLng);

            latIn.value = la.toFixed(6);
            lngIn.value = ln.toFixed(6);
            accIn.value = String(acc);
            if (updateAssignmentMapLocation) updateAssignmentMapLocation(la, ln, pos.coords.accuracy);

            status.textContent = 'Lokasi diperoleh: ±' + acc + ' m · ' + dist + ' m dari pusat tugasan.';
            status.classList.remove('bad');

            if (dist > radius) {
                warning.textContent = 'Anda berada ' + dist + ' m dari lokasi (radius ' + radius +
                    ' m). Catatan diperlukan sebelum menghantar.';
                warning.classList.remove('hidden');
            } else {
                warning.classList.add('hidden');
            }
        }

        function fail() {
            status.textContent = 'Lokasi tidak dapat dibaca. Anda masih boleh menghantar dengan catatan.';
            status.classList.add('bad');
        }

        locate().then(paint).catch(fail);

        var retry = $('#relocate');
        if (retry) {
            retry.addEventListener('click', function () {
                status.textContent = 'Mencari lokasi…';
                locate().then(paint).catch(fail);
            });
        }
    }

    /* --------------------------------------------------------- check-out */
    function initCheckout() {
        var form = $('#checkout-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            var lat = $('#out-lat'), lng = $('#out-lng');
            if (form.dataset.submitting === '1') {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            form.dataset.submitting = '1';

            var button = $('button[type="submit"]', form);
            var status = $('#checkout-status');
            var submitted = false;
            var fallbackTimer = window.setTimeout(function () {
                if (status) status.textContent = form.dataset.locationFailed || '';
                submitForm();
            }, 7000);

            function submitForm() {
                if (submitted) return;
                submitted = true;
                window.clearTimeout(fallbackTimer);
                if (button) button.disabled = true;
                HTMLFormElement.prototype.submit.call(form);
            }

            if (button) button.disabled = true;
            if (status) status.textContent = form.dataset.locating || '';

            if (!navigator.geolocation) {
                if (status) status.textContent = form.dataset.locationFailed || '';
                submitForm();
                return;
            }

            try {
                navigator.geolocation.getCurrentPosition(function (position) {
                    lat.value = position.coords.latitude.toFixed(6);
                    lng.value = position.coords.longitude.toFixed(6);
                    submitForm();
                }, function () {
                    if (status) status.textContent = form.dataset.locationFailed || '';
                    submitForm();
                }, {
                    enableHighAccuracy: true,
                    timeout: 6000,
                    maximumAge: 30000
                });
            } catch (error) {
                if (status) status.textContent = form.dataset.locationFailed || '';
                submitForm();
            }
        });
    }

    /* --------------------------------------------------- password visibility */
    function initPasswordToggles() {
        var malay = document.documentElement.lang === 'ms';
        var showLabel = malay ? 'Lihat kata laluan' : 'Show password';
        var hideLabel = malay ? 'Sembunyikan kata laluan' : 'Hide password';
        var eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            + '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path>'
            + '<circle cx="12" cy="12" r="3"></circle>'
            + '</svg>';
        var eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            + '<path d="M3 3l18 18"></path>'
            + '<path d="M10.6 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.9M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 3.1-.5"></path>'
            + '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path>'
            + '</svg>';

        $$('input[type="password"]').forEach(function (input) {
            var wrapper = document.createElement('span');
            wrapper.className = 'password-input-wrap';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'password-toggle';
            toggle.setAttribute('aria-label', showLabel);
            toggle.setAttribute('aria-pressed', 'false');
            toggle.innerHTML = eyeIcon;
            wrapper.appendChild(toggle);

            toggle.addEventListener('click', function () {
                var visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                toggle.setAttribute('aria-label', visible ? hideLabel : showLabel);
                toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
                toggle.innerHTML = visible ? eyeOffIcon : eyeIcon;
            });
        });
    }

    /* ---------------------------------------------------- offline queue */
    function queueCheckin(payload) {
        try {
            var key = 'ehadirgo.queue';
            var items = JSON.parse(localStorage.getItem(key) || '[]');
            items.push(payload);
            localStorage.setItem(key, JSON.stringify(items));
            return true;
        } catch (err) {
            return false;
        }
    }
    window.eHadirQueue = queueCheckin;

    /* ------------------------------------------------------------- boot */
    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggles();
        initViewMap();
        initPicker();
        initParticipantsPicker();
        initLiveMap();
        initCheckin();
        initCheckout();
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(new URL('sw.js', document.baseURI).pathname, { scope: './' })
                .catch(function () { /* offline support is optional */ });
        });
    }
})();
