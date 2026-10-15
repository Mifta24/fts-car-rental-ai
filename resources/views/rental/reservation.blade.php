
    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $rental->slug) }}"
        data-submit-url="{{ route('reservation.store', $rental->slug) }}"
        data-rental-slug="{{ $rental->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $rental->currency }}"
        data-today="{{ $today }}"
        data-max-days="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}"
        data-preselect-vehicle="{{ $preselectedVehicle ?? '' }}"
        data-prefill-pickup-date="{{ $prefill['pickup_date'] ?? '' }}"
        data-prefill-return-date="{{ $prefill['return_date'] ?? '' }}"
        data-prefill-passengers="{{ $prefill['passengers'] ?? '' }}"
    >
        <div data-wizard-flow>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step">
                    <label class="wizard-field">
                        <span>{{ $wizard['pickup_date'] }}</span>
                        <input type="date" name="pickup_date" min="{{ $today }}" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['return_date'] }}</span>
                        <input type="date" name="return_date" min="{{ $today }}" required>
                    </label>
                    <p class="wizard-hint" data-days-hint aria-live="polite"></p>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <label class="wizard-field">
                        <span>{{ $wizard['passengers'] }}</span>
                        <input type="number" name="passengers" min="1" max="20" value="2" inputmode="numeric" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['units'] }}</span>
                        <input type="number" name="units" min="1" max="10" value="1" inputmode="numeric" required>
                    </label>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_vehicle'] }}</legend>
                        <div class="wizard-vehicles">
                            @foreach ($vehicles as $vehicle)
                                <label class="wizard-vehicle" data-vehicle-option
                                    data-name="{{ $vehicle->translatedName($locale) }}"
                                    data-seats="{{ $vehicle->seats }}"
                                    data-driver="{{ $vehicle->driver_available ? 1 : 0 }}"
                                >
                                    <input type="radio" name="vehicle_slug" value="{{ $vehicle->slug }}">
                                    <span class="wizard-vehicle-body">
                                        <strong>{{ $vehicle->translatedName($locale) }}</strong>
                                        <span>{{ str_replace(':count', (string) $vehicle->maxPassengers(), $wizard['fits']) }}</span>
                                        <span class="wizard-vehicle-price">{{ $labels['from'] }} {{ $rental->currency }} {{ number_format((float) $vehicle->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                                        <span class="wizard-vehicle-hint" data-vehicle-hint></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-check" data-driver-field hidden>
                        <input type="checkbox" name="with_driver" value="1">
                        <span>{{ $wizard['with_driver'] }}</span>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="customer_name" maxlength="100" autocomplete="name" required>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === 'whatsapp')><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="btn btn-primary" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="btn btn-primary" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="panel-title">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="wizard-done-hint">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="btn btn-primary" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_rental'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_rental'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}" class="wizard-secondary">{{ $showroom['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
