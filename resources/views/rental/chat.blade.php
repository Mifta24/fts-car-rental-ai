        <div id="agent-app"
            class="chat-widget"
            data-inline="true"
            data-label-connection-error="{{ $showroom['connection_error'] }}"
            data-rental-slug="{{ $rental->slug }}"
            data-locale="{{ $locale }}"
            data-start-url="{{ route('agent.start', $rental->slug) }}"
            data-message-url="{{ route('agent.message', $rental->slug) }}"
            data-history-url="{{ route('agent.history', $rental->slug) }}"
            data-storage-key="agent_token_{{ $rental->slug }}"
            data-label-placeholder="{{ $labels['chat_placeholder'] }}"
            data-label-send="{{ $labels['chat_send'] }}"
            data-label-open="{{ $labels['chat_open'] }}"
            data-label-close="{{ $labels['chat_close'] }}"
            data-label-intro="{{ $labels['chat_intro'] }}"
            data-label-insurance-included="{{ $labels['insurance_included'] }}"
            data-label-max-passengers="{{ $labels['max_passengers'] }}"
            data-label-no-insurance="{{ $labels['chat_no_insurance'] }}"
            data-label-day="{{ $labels['chat_day'] }}"
            data-label-no-availability="{{ $labels['chat_no_availability'] }}"
            data-label-booking-received="{{ $labels['chat_booking_received'] }}"
            data-label-reference="{{ $labels['chat_reference'] }}"
            data-label-thinking="{{ $labels['thinking'] }}"
            data-label-handed-over="{{ $labels['handed_over'] }}"
            data-label-status-sent="{{ $labels['chat_status_sent'] }}"
            data-label-status-waiting="{{ $labels['chat_status_waiting'] }}"
            data-label-status-replied="{{ $labels['chat_status_replied'] }}"
            data-label-draft-title="{{ $labels['chat_draft_title'] }}"
            data-label-draft-body="{{ $labels['chat_draft_body'] }}"
            data-label-draft-keep="{{ $labels['chat_draft_keep'] }}"
            data-label-draft-discard="{{ $labels['chat_draft_discard'] }}"
            data-label-view-details="{{ $labels['view_details'] }}"
            data-label-vehicle-details-question="{{ $labels['vehicle_details_question'] }}"
            data-label-book-now="{{ $labels['book_now'] }}"
            data-label-menu-heading="{{ $labels['menu_heading'] }}"
            data-label-staff="{{ $labels['menu_staff'] }}"
            data-label-error="{{ $labels['chat_error'] }}"
            data-label-slow="{{ $labels['chat_slow'] }}"
            data-label-retry="{{ $labels['chat_retry'] }}"
            data-currency="{{ $rental->currency }}"
            data-showroom-url="{{ route('rental.show', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}"
            data-vehicle-url="{{ route('rental.vehicle', ['rentalSlug' => $rental->slug, 'vehicleSlug' => '__SLUG__', 'lang' => $locale]) }}"
            data-staff-url="{{ route('rental.staff', ['rentalSlug' => $rental->slug, 'lang' => $locale]) }}"
            data-reservation-url="{{ route('rental.reservation', ['rentalSlug' => $rental->slug, 'lang' => $locale, 'vehicle' => '__SLUG__']) }}"
        >
            <div class="chat-panel" role="dialog" aria-label="{{ $labels['chat_heading'] }}">
                <section id="agent-chat-log" class="chat-log" aria-label="{{ $labels['chat_heading'] }}" aria-hidden="true">
                    <header class="chat-log-header">
                        <span class="chat-mark chat-mark-lg" aria-hidden="true">AI</span>
                        <div class="min-w-0 flex-1">
                            <p class="chat-title">
                                {{ $labels['chat_heading'] }}
                                <span class="chat-online" aria-hidden="true"></span>
                            </p>
                            <p class="chat-subtitle">{{ $labels['chat_subtitle'] }}</p>
                            <p data-chat-status class="chat-status" aria-live="polite" hidden></p>
                        </div>
                        <button type="button" data-chat-new class="chat-log-new" title="{{ $labels['chat_new'] }}" aria-label="{{ $labels['chat_new'] }}">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <span>{{ $labels['chat_new'] }}</span>
                        </button>
                        <button type="button" data-chat-close class="chat-log-close" aria-label="{{ $labels['chat_close'] }}"><span aria-hidden="true">×</span></button>
                    </header>

                    <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="chat-log-messages space-y-3"></div>

                    <div data-thinking-indicator class="chat-thinking" role="status" hidden>
                        <span class="chat-mark" aria-hidden="true">AI</span>
                        <span class="chat-thinking-bubble">
                            <span class="chat-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <span>{{ $labels['thinking'] }}</span>
                        </span>
                    </div>

                    <div data-status-banner class="chat-banner hidden"></div>

                    {{-- Each chip opens its page; the agent then carries on with that topic. --}}
                    @php
                        $chipLabels = ['vehicles' => $labels['menu_vehicles'], 'services' => $labels['menu_services'], 'info' => $labels['menu_policies'], 'reservation' => $showroom['start_booking'], 'staff' => $labels['menu_staff']];
                    @endphp
                    <ul class="chat-chips" aria-label="{{ $labels['menu_heading'] }}">
                        @foreach ($menuItems as $item)
                            <li><a href="{{ $item['href'] }}" data-topic="{{ $item['topic'] }}">{{ $chipLabels[$item['key']] ?? $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </section>

                <form data-chat-form data-chat-composer class="chat-bar" aria-hidden="true">
                    <input
                        type="text"
                        data-chat-input
                        aria-label="{{ $labels['chat_placeholder'] }}"
                        maxlength="4000"
                        placeholder="{{ $labels['chat_placeholder'] }}"
                        autocomplete="off"
                    >
                    <button type="submit" data-chat-submit>{{ $labels['chat_send'] }}</button>
                </form>

                <div data-draft-dialog class="chat-draft-dialog" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-labelledby="chat-draft-title">
                    <p id="chat-draft-title" data-draft-title class="font-semibold text-slate-900">{{ $labels['chat_draft_title'] }}</p>
                    <p data-draft-body class="mt-1 text-xs leading-relaxed text-slate-600">{{ $labels['chat_draft_body'] }}</p>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" data-draft-keep class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700">{{ $labels['chat_draft_keep'] }}</button>
                        <button type="button" data-draft-discard class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-medium text-white">{{ $labels['chat_draft_discard'] }}</button>
                    </div>
                </div>
            </div>

            <button type="button" data-chat-launcher class="chat-launcher" aria-controls="agent-chat-log" aria-expanded="false">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5.5h14a1.5 1.5 0 0 1 1.5 1.5v8.5a1.5 1.5 0 0 1-1.5 1.5h-7l-4.5 3.5V17H5a1.5 1.5 0 0 1-1.5-1.5V7A1.5 1.5 0 0 1 5 5.5Z"/></svg>
                <span class="chat-launcher-text"><strong>{{ $labels['chat_heading'] }}</strong><small>{{ $showroom['available'] }}</small></span>
                <span class="chat-launcher-dot" aria-hidden="true"></span>
            </button>
        </div>
