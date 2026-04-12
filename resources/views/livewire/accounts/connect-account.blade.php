<div class="space-y-8">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-fluid-lg font-bold text-sand-900">Connect Account</h1>
            <a href="{{ route('accounts.index') }}" class="text-sm text-sand-500 transition-colors hover:text-sand-800">Back to Accounts</a>
        </div>

        <div class="rounded-xl border border-sand-200 bg-white p-10 text-center"
            x-data="connectAccount(@js($stripePublishableKey))"
        >
            <x-phosphor-link class="mx-auto mb-4 h-10 w-10 text-amber-400" />
            <p class="mb-6 text-sand-600">Securely connect your bank account to start tracking your spending.</p>

            <template x-if="error">
                <p class="mb-4 text-sm text-red-600" x-text="error"></p>
            </template>

            <button
                @click="connect()"
                :disabled="loading"
                class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-amber-600 disabled:opacity-50"
            >
                <x-phosphor-bank class="h-4 w-4" />
                <span x-text="loading ? 'Connecting...' : 'Connect Bank Account'"></span>
            </button>
        </div>
    </div>

    @push('scripts')
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('connectAccount', (publishableKey) => ({
                loading: false,
                error: null,

                async connect() {
                    this.loading = true;
                    this.error = null;

                    try {
                        const stripe = Stripe(publishableKey);
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                        const sessionResponse = await fetch('{{ route("connections.session") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                        });

                        if (!sessionResponse.ok) {
                            throw new Error('Failed to create connection session.');
                        }

                        const { client_secret } = await sessionResponse.json();

                        const result = await stripe.collectFinancialConnectionsAccounts({
                            clientSecret: client_secret,
                        });

                        if (result.error) {
                            if (result.error.code === 'session_cancelled') {
                                this.loading = false;
                                return;
                            }
                            throw new Error(result.error.message || 'Connection failed.');
                        }

                        const accountIds = result.financialConnectionsSession.accounts.map(a => a.id);

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route("connections.store") }}';

                        const csrf = document.createElement('input');
                        csrf.type = 'hidden';
                        csrf.name = '_token';
                        csrf.value = csrfToken;
                        form.appendChild(csrf);

                        accountIds.forEach((id, index) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = `stripe_account_ids[${index}]`;
                            input.value = id;
                            form.appendChild(input);
                        });

                        document.body.appendChild(form);
                        form.submit();
                    } catch (e) {
                        this.error = e.message || 'Something went wrong. Please try again.';
                        this.loading = false;
                    }
                },
            }));
        });
    </script>
    @endpush
</div>
