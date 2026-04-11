<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cashbird — Your family's finances, organized</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|fraunces:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Animated gradient mesh background */
        .gradient-mesh {
            background:
                radial-gradient(ellipse 80% 60% at 20% 40%, oklch(0.92 0.06 80 / 0.6), transparent),
                radial-gradient(ellipse 60% 80% at 80% 20%, oklch(0.95 0.03 145 / 0.4), transparent),
                radial-gradient(ellipse 70% 50% at 60% 80%, oklch(0.96 0.04 30 / 0.3), transparent),
                oklch(0.985 0.005 85);
            background-size: 200% 200%;
            animation: meshShift 20s ease-in-out infinite;
        }
        @keyframes meshShift {
            0%, 100% { background-position: 0% 50%; }
            33% { background-position: 100% 20%; }
            66% { background-position: 30% 80%; }
        }

        /* Hero word reveal */
        .word-reveal span {
            display: inline-block;
            opacity: 0;
            transform: translateY(20px);
            animation: wordIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes wordIn {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Floating card effect */
        .float-card {
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .float-card:hover {
            transform: translateY(-8px) rotate(-1deg);
        }

        /* Parallax tilt on feature mockups */
        .mockup-tilt {
            transform: perspective(1200px) rotateY(-6deg) rotateX(3deg);
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .mockup-tilt:hover {
            transform: perspective(1200px) rotateY(-2deg) rotateX(1deg);
        }

        /* Glow behind hero */
        .hero-glow {
            position: absolute;
            width: 600px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, oklch(0.81 0.155 68 / 0.2), transparent 70%);
            filter: blur(80px);
            pointer-events: none;
            animation: glowPulse 6s ease-in-out infinite;
        }
        @keyframes glowPulse {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.1); }
        }

        /* Step counter pulse */
        .step-ring {
            position: relative;
        }
        .step-ring::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 9999px;
            border: 2px solid oklch(0.81 0.155 68 / 0.3);
            animation: ringPulse 3s ease-in-out infinite;
        }
        @keyframes ringPulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.3); opacity: 0; }
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            .gradient-mesh { animation: none; }
            .reveal { opacity: 1; transform: none; }
            .word-reveal span { opacity: 1; transform: none; animation: none; }
            .hero-glow { animation: none; opacity: 0.5; }
            .step-ring::after { animation: none; }
            .float-card:hover { transform: none; }
            .mockup-tilt { transform: none; }
            .mockup-tilt:hover { transform: none; }
        }
    </style>
</head>
<body class="font-sans text-sand-900 antialiased overflow-x-hidden">

    {{-- Animated gradient mesh background --}}
    <div class="gradient-mesh fixed inset-0 -z-10"></div>

    {{-- Nav --}}
    <header class="relative z-10 px-6 py-5">
        <div class="mx-auto flex max-w-5xl items-center justify-between">
            <span class="font-display text-xl font-bold tracking-tight text-sand-900">Cashbird</span>
            <a href="{{ route('login') }}" class="rounded-full bg-sand-900 px-5 py-2 text-sm font-medium text-sand-50 transition-all hover:bg-sand-800 hover:shadow-lg hover:shadow-sand-900/10">
                Sign in
            </a>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative px-6 pb-32 pt-20 sm:pt-28">
        {{-- Ambient glow --}}
        <div class="hero-glow -top-20 left-1/2 -translate-x-1/2"></div>

        <div class="relative mx-auto max-w-5xl">
            <h1 class="word-reveal font-display text-5xl font-extrabold leading-[1.15] tracking-tight text-sand-900 sm:text-7xl sm:leading-[1.12] lg:text-8xl">
                <span style="animation-delay: 0s">Know</span>
                <span style="animation-delay: 0.08s">what</span>
                <span style="animation-delay: 0.16s">you</span>
                <span style="animation-delay: 0.24s">can</span><br class="hidden sm:block">
                <span style="animation-delay: 0.32s">spend</span>
                <span class="text-amber-500" style="animation-delay: 0.4s">today.</span>
            </h1>
            <p class="reveal mt-8 max-w-lg text-lg leading-relaxed text-sand-600 sm:text-xl" style="transition-delay: 0.5s">
                Cashbird is our household finance app. It keeps track of what we're spending, how our budget is doing, and where our money is actually going.
            </p>
            <div class="reveal mt-10 flex items-center gap-4" style="transition-delay: 0.65s">
                <a href="{{ route('login') }}" class="group relative inline-flex items-center gap-2 rounded-full bg-amber-500 px-7 py-3.5 text-sm font-semibold text-white shadow-lg shadow-amber-500/25 transition-all hover:bg-amber-600 hover:shadow-xl hover:shadow-amber-500/30 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                    Sign in to Cashbird
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                </a>
                <span class="text-sm text-sand-400">For our family</span>
            </div>
        </div>

        {{-- Floating dashboard mockup --}}
        <div class="reveal mx-auto mt-20 max-w-4xl" style="transition-delay: 0.8s">
            <div class="mockup-tilt rounded-2xl border border-sand-200/60 bg-white/70 p-6 shadow-2xl shadow-sand-900/5 backdrop-blur-sm sm:p-8" aria-hidden="true">
                {{-- Fake dashboard header --}}
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <p class="font-display text-sm text-sand-400">Good morning</p>
                        <p class="font-display text-2xl font-semibold text-sand-900">Dashboard</p>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-3 w-3 rounded-full bg-sage-300"></div>
                        <div class="h-3 w-3 rounded-full bg-amber-300"></div>
                        <div class="h-3 w-3 rounded-full bg-terracotta-300"></div>
                    </div>
                </div>
                {{-- Hero card mockup --}}
                <div class="rounded-xl bg-amber-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-600">Safe to Spend Today</p>
                    <p class="mt-2 font-display text-4xl font-bold text-sand-900">$89.11</p>
                    <p class="mt-1 text-sm text-sand-500">$1,247.50 left this month</p>
                </div>
                {{-- Mini stats row --}}
                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div class="rounded-lg bg-sand-50 p-3">
                        <div class="h-2 w-12 rounded bg-sand-200"></div>
                        <div class="mt-2 h-4 w-16 rounded bg-sand-300"></div>
                    </div>
                    <div class="rounded-lg bg-sand-50 p-3">
                        <div class="h-2 w-10 rounded bg-sand-200"></div>
                        <div class="mt-2 h-4 w-14 rounded bg-sage-300"></div>
                    </div>
                    <div class="rounded-lg bg-sand-50 p-3">
                        <div class="h-2 w-14 rounded bg-sand-200"></div>
                        <div class="mt-2 h-4 w-12 rounded bg-amber-300"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="relative px-6 py-24">
        <div class="mx-auto max-w-5xl space-y-40">

            {{-- Budget --}}
            <div class="reveal grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-amber-100/80 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-amber-700">
                        <x-phosphor-chart-pie-slice-fill class="h-4 w-4" />
                        Budget
                    </div>
                    <h2 class="mt-6 font-display text-4xl font-bold leading-tight text-sand-900 sm:text-5xl">
                        A budget that fits how you <em class="not-italic text-amber-600">actually</em> spend.
                    </h2>
                    <p class="mt-5 text-lg leading-relaxed text-sand-500">
                        Cashbird analyzes your spending history and builds a budget that makes sense. See what's left in each category and know exactly how much is safe to spend today.
                    </p>
                </div>
                <div class="float-card rounded-2xl border border-sand-200/60 bg-white/70 p-6 shadow-xl shadow-sand-900/5 backdrop-blur-sm" aria-hidden="true">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-600">Your categories — April 2026</p>
                    <div class="mt-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-sand-800">Groceries</span>
                            <span class="text-sm text-sage-600">$124 left</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-sand-100"><div class="h-2 rounded-full bg-amber-400" style="width: 68%"></div></div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-sand-800">Dining out</span>
                            <span class="text-sm text-terracotta-600">$12 left</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-sand-100"><div class="h-2 rounded-full bg-terracotta-400" style="width: 94%"></div></div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-sand-800">Entertainment</span>
                            <span class="text-sm text-sage-600">$85 left</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-sand-100"><div class="h-2 rounded-full bg-sage-400" style="width: 43%"></div></div>
                    </div>
                </div>
            </div>

            {{-- Spending --}}
            <div class="reveal grid items-center gap-16 lg:grid-cols-2">
                <div class="order-2 float-card rounded-2xl border border-sand-200/60 bg-white/70 p-6 shadow-xl shadow-sand-900/5 backdrop-blur-sm lg:order-1" aria-hidden="true">
                    <p class="text-xs font-medium uppercase tracking-wide text-sage-600">Where your money went — March</p>
                    <div class="mt-4 space-y-2.5">
                        @foreach([['Groceries', '$412.30', '52%'], ['Dining', '$189.44', '24%'], ['Gas', '$98.10', '12%'], ['Subscriptions', '$67.88', '8%']] as [$cat, $amt, $pct])
                            <div class="flex items-center gap-3 rounded-lg bg-sand-50/80 px-3 py-2.5">
                                <div class="h-8 w-8 rounded-lg bg-{{ $loop->index === 0 ? 'amber' : ($loop->index === 1 ? 'sage' : ($loop->index === 2 ? 'terracotta' : 'sand')) }}-200 shrink-0"></div>
                                <span class="flex-1 text-sm font-medium text-sand-800">{{ $cat }}</span>
                                <span class="text-sm text-sand-500">{{ $amt }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-sage-100/80 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-sage-700">
                        <x-phosphor-arrows-left-right-fill class="h-4 w-4" />
                        Spending
                    </div>
                    <h2 class="mt-6 font-display text-4xl font-bold leading-tight text-sand-900 sm:text-5xl">
                        Every dollar, sorted and <em class="not-italic text-sage-600">categorized.</em>
                    </h2>
                    <p class="mt-5 text-lg leading-relaxed text-sand-500">
                        Your transactions flow in automatically and get organized. See where the money goes each month — no surprises, no digging through statements.
                    </p>
                </div>
            </div>

            {{-- Insights --}}
            <div class="reveal grid items-center gap-16 lg:grid-cols-2">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-amber-100/80 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-amber-700">
                        <x-phosphor-lightbulb-fill class="h-4 w-4" />
                        Insights
                    </div>
                    <h2 class="mt-6 font-display text-4xl font-bold leading-tight text-sand-900 sm:text-5xl">
                        Heads up before it's a <em class="not-italic text-amber-600">problem.</em>
                    </h2>
                    <p class="mt-5 text-lg leading-relaxed text-sand-500">
                        Cashbird spots patterns — unusual charges, categories running hot, trends worth knowing about. You get a heads up before things slip.
                    </p>
                </div>
                <div class="float-card space-y-3" aria-hidden="true">
                    <div class="rounded-xl border border-amber-200/60 bg-amber-50/80 p-5 shadow-lg shadow-amber-500/5 backdrop-blur-sm">
                        <div class="flex items-center gap-2">
                            <x-phosphor-warning-fill class="h-4 w-4 text-amber-500" />
                            <span class="text-sm font-semibold text-amber-800">Dining spending up 34%</span>
                        </div>
                        <p class="mt-1.5 text-sm text-amber-700/70">You've spent $189 on dining this month — that's $48 more than your 3-month average.</p>
                    </div>
                    <div class="rounded-xl border border-sage-200/60 bg-sage-50/80 p-5 shadow-lg shadow-sage-500/5 backdrop-blur-sm">
                        <div class="flex items-center gap-2">
                            <x-phosphor-trend-down-fill class="h-4 w-4 text-sage-500" />
                            <span class="text-sm font-semibold text-sage-800">Subscriptions dropped $12</span>
                        </div>
                        <p class="mt-1.5 text-sm text-sage-700/70">Nice — that cancelled streaming service saved you $11.99 this month.</p>
                    </div>
                </div>
            </div>

            {{-- Reports --}}
            <div class="reveal grid items-center gap-16 lg:grid-cols-2">
                <div class="order-2 float-card rounded-2xl border border-sand-200/60 bg-white/70 p-6 shadow-xl shadow-sand-900/5 backdrop-blur-sm lg:order-1" aria-hidden="true">
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-medium uppercase tracking-wide text-terracotta-500">Monthly Report — March 2026</p>
                    </div>
                    <div class="space-y-2">
                        <div class="h-3 w-3/4 rounded bg-sand-200/80"></div>
                        <div class="h-3 w-full rounded bg-sand-200/80"></div>
                        <div class="h-3 w-5/6 rounded bg-sand-200/80"></div>
                        <div class="h-3 w-2/3 rounded bg-sand-200/80"></div>
                    </div>
                    <div class="mt-5 flex items-end gap-1.5" style="height: 60px">
                        @foreach([40, 55, 35, 65, 50, 70, 45] as $h)
                            <div class="flex-1 rounded-t bg-{{ $loop->index % 3 === 0 ? 'amber' : ($loop->index % 3 === 1 ? 'sage' : 'terracotta') }}-300" style="height: {{ $h }}%"></div>
                        @endforeach
                    </div>
                </div>
                <div class="order-1 lg:order-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-terracotta-100/80 px-4 py-1.5 text-xs font-semibold uppercase tracking-wider text-terracotta-700">
                        <x-phosphor-file-text-fill class="h-4 w-4" />
                        Reports
                    </div>
                    <h2 class="mt-6 font-display text-4xl font-bold leading-tight text-sand-900 sm:text-5xl">
                        The full picture, whenever you <em class="not-italic text-terracotta-500">want it.</em>
                    </h2>
                    <p class="mt-5 text-lg leading-relaxed text-sand-500">
                        Monthly summaries, spending breakdowns, trends over time. Generated automatically on the 1st — just open and read.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="reveal relative px-6 py-28">
        <div class="mx-auto max-w-5xl text-center">
            <h2 class="font-display text-4xl font-bold text-sand-900 sm:text-5xl">Three steps. That's it.</h2>
            <p class="mx-auto mt-4 max-w-md text-lg text-sand-500">No spreadsheets. No manual entry. Just connect and go.</p>

            <div class="mt-20 grid gap-12 sm:grid-cols-3 sm:gap-8">
                <div>
                    <div class="step-ring mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-500 font-display text-xl font-bold text-white shadow-lg shadow-amber-500/20">1</div>
                    <h3 class="mt-6 font-display text-xl font-semibold text-sand-900">Connect your bank</h3>
                    <p class="mt-3 text-sand-500">Link your accounts securely. Cashbird pulls in transactions automatically.</p>
                </div>
                <div>
                    <div class="step-ring mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-500 font-display text-xl font-bold text-white shadow-lg shadow-amber-500/20">2</div>
                    <h3 class="mt-6 font-display text-xl font-semibold text-sand-900">Cashbird organizes everything</h3>
                    <p class="mt-3 text-sand-500">Transactions are categorized and matched against your budget as they come in.</p>
                </div>
                <div>
                    <div class="step-ring mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-500 font-display text-xl font-bold text-white shadow-lg shadow-amber-500/20">3</div>
                    <h3 class="mt-6 font-display text-xl font-semibold text-sand-900">See your full picture</h3>
                    <p class="mt-3 text-sand-500">Budget, spending, insights, and reports — always up to date.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="reveal relative px-6 py-24">
        <div class="mx-auto max-w-5xl text-center">
            <h2 class="font-display text-4xl font-bold text-sand-900 sm:text-5xl">Ready?</h2>
            <div class="mt-8">
                <a href="{{ route('login') }}" class="group relative inline-flex items-center gap-2 rounded-full bg-sand-900 px-8 py-4 text-base font-semibold text-sand-50 shadow-xl shadow-sand-900/10 transition-all hover:bg-sand-800 hover:shadow-2xl hover:shadow-sand-900/15 focus:outline-none focus:ring-2 focus:ring-sand-900 focus:ring-offset-2">
                    Sign in to Cashbird
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-sand-200/50 px-6 py-10">
        <div class="mx-auto flex max-w-5xl items-center justify-between">
            <span class="font-display text-base font-semibold text-sand-900">Cashbird</span>
            <span class="text-sm text-sand-400">Made for our family</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/gsap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gsap@3/dist/ScrollTrigger.min.js"></script>
    <script>
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            gsap.registerPlugin(ScrollTrigger);

            // Each .reveal gets its own ScrollTrigger
            document.querySelectorAll('.reveal').forEach(el => {
                // Skip grids — they're handled by the feature section logic below
                if (el.classList.contains('grid')) return;

                gsap.from(el, {
                    y: 40,
                    opacity: 0,
                    duration: 0.9,
                    ease: 'expo.out',
                    scrollTrigger: { trigger: el, start: 'top 85%', once: true },
                });
            });

            // Dashboard mockup — parallax float
            const mockup = document.querySelector('.mockup-tilt');
            if (mockup) {
                gsap.to(mockup, {
                    y: -60,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: mockup,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: 1.5,
                    },
                });
            }

            // Feature sections — text staggers in, card slides from the side
            document.querySelectorAll('.reveal.grid').forEach((section, i) => {
                const cols = [...section.children];
                const textCol = cols.find(c => !c.hasAttribute('aria-hidden'));
                const cardCol = cols.find(c => c.hasAttribute('aria-hidden'));
                const fromRight = i % 2 === 0;

                // Reveal the grid container itself
                gsap.from(section, {
                    opacity: 0,
                    duration: 0.1,
                    scrollTrigger: { trigger: section, start: 'top 80%', once: true },
                });

                if (textCol) {
                    gsap.from(textCol.children, {
                        y: 30,
                        opacity: 0,
                        duration: 0.7,
                        ease: 'expo.out',
                        stagger: 0.1,
                        scrollTrigger: { trigger: section, start: 'top 75%', once: true },
                    });
                }
                if (cardCol) {
                    gsap.from(cardCol, {
                        x: fromRight ? 80 : -80,
                        opacity: 0,
                        duration: 1,
                        ease: 'expo.out',
                        scrollTrigger: { trigger: section, start: 'top 75%', once: true },
                    });
                }
            });

            // Float cards — subtle continuous bob
            document.querySelectorAll('.float-card').forEach((card, i) => {
                gsap.to(card, {
                    y: -10,
                    rotation: i % 2 === 0 ? 1 : -1,
                    ease: 'sine.inOut',
                    duration: 3 + i * 0.5,
                    repeat: -1,
                    yoyo: true,
                });
            });

            // How it works — steps pop in with stagger
            const steps = document.querySelectorAll('.step-ring');
            steps.forEach((ring, i) => {
                const stepEl = ring.closest('div[style]') || ring.parentElement;
                gsap.from(stepEl, {
                    y: 40,
                    opacity: 0,
                    scale: 0.9,
                    duration: 0.7,
                    ease: 'back.out(1.4)',
                    scrollTrigger: {
                        trigger: stepEl,
                        start: 'top 85%',
                        once: true,
                    },
                });
            });

            // Progress bars — animate width on scroll
            document.querySelectorAll('.float-card .overflow-hidden .h-2').forEach(bar => {
                const targetWidth = bar.style.width;
                if (!targetWidth) return;
                bar.style.width = '0%';
                gsap.to(bar, {
                    width: targetWidth,
                    duration: 1.2,
                    ease: 'expo.out',
                    scrollTrigger: { trigger: bar, start: 'top 90%', once: true },
                });
            });
        }
    </script>
</body>
</html>
