<div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Connect Account</h1>
            <a href="{{ route('accounts.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to Accounts</a>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <p class="mb-4 text-gray-600">Click below to securely connect your bank account via Teller.</p>
            <button
                id="teller-connect-btn"
                class="rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white hover:bg-gray-800"
            >
                Connect Bank Account
            </button>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.teller.io/connect/connect.js"></script>
    <script>
        document.getElementById('teller-connect-btn').addEventListener('click', function () {
            const tellerConnect = TellerConnect.setup({
                applicationId: @js($appId),
                onSuccess: function (enrollment) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("teller.store") }}';

                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = '{{ csrf_token() }}';
                    form.appendChild(csrf);

                    const fields = {
                        'access_token': enrollment.accessToken,
                        'enrollment_id': enrollment.enrollment.id,
                        'institution[id]': enrollment.enrollment.institution.id,
                        'institution[name]': enrollment.enrollment.institution.name,
                    };

                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                },
                onExit: function () {},
            });
            tellerConnect.open();
        });
    </script>
    @endpush
</div>
