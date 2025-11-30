<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Lipa Maji - Transform your utility operations with accurate bills, faster collections, and zero disputes. Professional water billing management for modern utilities.">

        <title>Lipa Maji - Accurate Bills. Faster Collections. Zero Disputes.</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900|space-grotesk:700" rel="stylesheet" />

        <!-- Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
        
        <!-- AOS Animation Library -->
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
        <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
        
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            'inter': ['Inter', 'sans-serif'],
                            'grotesk': ['Space Grotesk', 'sans-serif'],
                        },
                        colors: {
                            primary: {
                                50: '#eef2ff',
                                100: '#e0e7ff',
                                200: '#c7d2fe',
                                300: '#a5b4fc',
                                400: '#818cf8',
                                500: '#6366f1',
                                600: '#4f46e5',
                                700: '#4338ca',
                                800: '#3730a3',
                                900: '#312e81',
                            },
                            accent: {
                                400: '#34d399',
                                500: '#10b981',
                                600: '#059669',
                            }
                        },
                        animation: {
                            'float': 'float 8s ease-in-out infinite',
                            'float-delayed': 'float 8s ease-in-out 2s infinite',
                            'float-delayed-2': 'float 8s ease-in-out 4s infinite',
                            'slide-up': 'slideUp 0.6s ease-out',
                            'fade-in': 'fadeIn 0.8s ease-out',
                            'scale-in': 'scaleIn 0.5s ease-out',
                            'shimmer': 'shimmer 2s linear infinite',
                            'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        },
                        keyframes: {
                            float: {
                                '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                                '33%': { transform: 'translateY(-20px) rotate(2deg)' },
                                '66%': { transform: 'translateY(-10px) rotate(-2deg)' },
                            },
                            slideUp: {
                                '0%': { transform: 'translateY(30px)', opacity: '0' },
                                '100%': { transform: 'translateY(0px)', opacity: '1' },
                            },
                            fadeIn: {
                                '0%': { opacity: '0' },
                                '100%': { opacity: '1' },
                            },
                            scaleIn: {
                                '0%': { transform: 'scale(0.95)', opacity: '0' },
                                '100%': { transform: 'scale(1)', opacity: '1' },
                            },
                            shimmer: {
                                '0%': { backgroundPosition: '-1000px 0' },
                                '100%': { backgroundPosition: '1000px 0' },
                            }
                        },
                        backgroundImage: {
                            'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                            'shimmer': 'linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent)',
                        }
                    }
                }
            }
        </script>
        
        <style>
            .glass {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .glass-white {
                background: rgba(255, 255, 255, 0.9);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            
            .text-gradient {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .bg-gradient-mesh {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            }
            
            .hover-lift {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .hover-lift:hover {
                transform: translateY(-8px);
            }
            
            .shimmer {
                position: relative;
                overflow: hidden;
            }
            
            .shimmer::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
                animation: shimmer 2s infinite;
            }
        </style>
    </head>
    <body class="font-inter antialiased bg-slate-50 overflow-x-hidden">

        <!-- Navigation Bar -->
        <nav class="fixed top-0 left-0 right-0 z-50 glass-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16 md:h-20">
                    <div class="flex items-center space-x-2 md:space-x-3">
                        <img src="{{ asset('logo.png') }}" alt="Lipa Maji" class="h-10 md:h-12 w-auto">
                        <div class="flex flex-col">
                            <span class="text-lg md:text-2xl font-grotesk font-bold bg-gradient-to-r from-primary-600 to-primary-800 bg-clip-text text-transparent leading-tight">
                                Lipa Maji
                            </span>
                            <span class="text-[10px] md:text-xs text-slate-500 -mt-0.5 md:-mt-1">Hydra Billing System</span>
                        </div>
                    </div>
                    
                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="#features" class="text-slate-600 hover:text-primary-600 font-medium transition-colors">Features</a>
                        <a href="#how-it-works" class="text-slate-600 hover:text-primary-600 font-medium transition-colors">How It Works</a>
                        <a href="#benefits" class="text-slate-600 hover:text-primary-600 font-medium transition-colors">Benefits</a>
                        @auth
                            <a href="/{{ config('filament.tenant.path') }}" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-primary-500/30 transition-all">
                                Dashboard
                            </a>
                        @else
                            <a href="/{{ config('filament.tenant.path') }}/login" class="px-6 py-2.5 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-primary-500/30 transition-all">
                                Sign In
                            </a>
                        @endauth
                    </div>

                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden border-t border-slate-200 bg-white">
                <div class="px-4 py-4 space-y-3">
                    <a href="#features" class="block px-4 py-2 text-slate-600 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-colors font-medium">
                        Features
                    </a>
                    <a href="#how-it-works" class="block px-4 py-2 text-slate-600 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-colors font-medium">
                        How It Works
                    </a>
                    <a href="#benefits" class="block px-4 py-2 text-slate-600 hover:bg-primary-50 hover:text-primary-600 rounded-lg transition-colors font-medium">
                        Benefits
                    </a>
                    @auth
                        <a href="/{{ config('filament.tenant.path') }}" class="block px-4 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl text-center hover:shadow-lg transition-all">
                            Dashboard
                        </a>
                    @else
                        <a href="/{{ config('filament.tenant.path') }}/login" class="block px-4 py-3 bg-gradient-to-r from-primary-600 to-primary-700 text-white font-semibold rounded-xl text-center hover:shadow-lg transition-all">
                            Sign In
                        </a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="relative min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-primary-900 to-slate-900 overflow-hidden pt-16 md:pt-20 pb-12 md:pb-0">
            <!-- Animated Background -->
            <div class="absolute inset-0">
                <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary-500/20 rounded-full mix-blend-lighten filter blur-3xl animate-float"></div>
                <div class="absolute top-1/3 right-1/4 w-96 h-96 bg-accent-500/20 rounded-full mix-blend-lighten filter blur-3xl animate-float-delayed"></div>
                <div class="absolute bottom-1/4 left-1/3 w-96 h-96 bg-primary-400/15 rounded-full mix-blend-lighten filter blur-3xl animate-float-delayed-2"></div>
            </div>
            
            <!-- Grid Pattern Overlay -->
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI2MCIgaGVpZ2h0PSI2MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSAxMCAwIEwgMCAwIDAgMTAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgyNTUsMjU1LDI1NSwwLjAzKSIgc3Ryb2tlLXdpZHRoPSIxIi8+PC9wYXR0ZXJuPjwvZGVmcz48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSJ1cmwoI2dyaWQpIi8+PC9zdmc+')] opacity-20"></div>
            
            <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-8 md:py-0">
                
                <!-- Trust Badge -->
                <div class="inline-flex items-center space-x-2 px-3 md:px-4 py-1.5 md:py-2 bg-white/10 backdrop-blur-sm rounded-full border border-white/20 mb-6 md:mb-8" data-aos="fade-down">
                    <span class="w-2 h-2 bg-accent-400 rounded-full animate-pulse"></span>
                    <span class="text-white/90 text-xs md:text-sm font-medium">Trusted by 100+ Organizations</span>
                </div>
                
                <!-- Main Headline -->
                <h1 class="text-3xl sm:text-4xl md:text-6xl lg:text-7xl xl:text-8xl font-grotesk font-bold text-white mb-6 md:mb-8 px-2" data-aos="fade-up" data-aos-delay="100">
                    Accurate Bills.
                    <span class="block bg-gradient-to-r from-accent-400 via-primary-400 to-accent-500 bg-clip-text text-transparent">
                        Faster Collections.
                    </span>
                    <span class="block text-white/90">Zero Disputes.</span>
                </h1>
                
                <!-- Subheadline -->
                <p class="text-base sm:text-lg md:text-xl lg:text-2xl text-slate-300 mb-6 md:mb-8 max-w-3xl mx-auto leading-relaxed px-4" data-aos="fade-up" data-aos-delay="200">
                    Transform your utility billing with outcomes that matter: <span class="text-white font-semibold">95% on-time payments</span>, 
                    <span class="text-white font-semibold">3-day billing cycles</span>, and <span class="text-white font-semibold"><3% disputes</span>.
                </p>
                
                <!-- Metrics Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 max-w-4xl mx-auto mb-8 md:mb-12 px-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="glass p-4 md:p-6 rounded-2xl border border-white/10">
                        <div class="text-3xl md:text-4xl font-bold text-accent-400 mb-1 md:mb-2">95%</div>
                        <div class="text-xs md:text-sm text-slate-300">On-Time Payments</div>
                    </div>
                    <div class="glass p-4 md:p-6 rounded-2xl border border-white/10">
                        <div class="text-3xl md:text-4xl font-bold text-primary-400 mb-1 md:mb-2">3-Day</div>
                        <div class="text-xs md:text-sm text-slate-300">Billing Cycles</div>
                    </div>
                    <div class="glass p-4 md:p-6 rounded-2xl border border-white/10">
                        <div class="text-3xl md:text-4xl font-bold text-accent-400 mb-1 md:mb-2"><3%</div>
                        <div class="text-xs md:text-sm text-slate-300">Dispute Rate</div>
                    </div>
                </div>
                
                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 md:gap-4 px-4" data-aos="fade-up" data-aos-delay="400">
                    @auth
                        <a href="/{{ config('filament.tenant.path') }}" 
                           class="w-full sm:w-auto group px-6 md:px-8 py-3 md:py-4 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-base md:text-lg font-semibold rounded-2xl hover:from-primary-500 hover:to-primary-600 transition-all duration-300 shadow-2xl shadow-primary-500/30 hover:-translate-y-1 inline-flex items-center justify-center">
                            Access Dashboard
                            <svg class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                    @else
                        <a href="/{{ config('filament.tenant.path') }}/login" 
                           class="w-full sm:w-auto group px-6 md:px-8 py-3 md:py-4 bg-gradient-to-r from-primary-600 to-primary-700 text-white text-base md:text-lg font-semibold rounded-2xl hover:from-primary-500 hover:to-primary-600 transition-all duration-300 shadow-2xl shadow-primary-500/30 hover:-translate-y-1 inline-flex items-center justify-center">
                            Get Started
                            <svg class="ml-2 w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <a href="#how-it-works" 
                           class="w-full sm:w-auto px-6 md:px-8 py-3 md:py-4 bg-white/10 backdrop-blur-sm text-white text-base md:text-lg font-semibold rounded-2xl hover:bg-white/20 transition-all duration-300 border border-white/20 inline-flex items-center justify-center">
                            See How It Works
                        </a>
                    @endauth
                </div>
            </div>
            
            <!-- Scroll Indicator -->
            <div class="absolute bottom-10 left-1/2 -translate-x-1/2" data-aos="fade-up" data-aos-delay="600">
                <div class="w-6 h-10 border-2 border-white/30 rounded-full flex items-start justify-center p-2">
                    <div class="w-1 h-3 bg-white/60 rounded-full animate-bounce"></div>
                </div>
            </div>
        </section>

        <!-- What Hydra Guarantees Section -->
        <section id="benefits" class="py-16 md:py-24 lg:py-32 bg-white relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- Section Header -->
                <div class="text-center mb-12 md:mb-16 lg:mb-20 px-4" data-aos="fade-up">
                    <span class="inline-block px-3 md:px-4 py-1.5 md:py-2 bg-primary-100 text-primary-700 rounded-full text-xs md:text-sm font-semibold mb-3 md:mb-4">
                        OUTCOMES THAT MATTER
                    </span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-grotesk font-bold text-slate-900 mb-4 md:mb-6">
                        What Lipa Maji <span class="text-gradient">Guarantees</span>
                    </h2>
                    <p class="text-base md:text-lg lg:text-xl text-slate-600 max-w-3xl mx-auto">
                        We don't just provide features—we deliver measurable business outcomes that transform your operations
                    </p>
                </div>

                <!-- Guarantees Grid -->
                <div class="grid md:grid-cols-2 gap-6 md:gap-8">
                    
                    <!-- Guarantee 1 -->
                    <div class="group relative p-6 md:p-8 lg:p-10 bg-gradient-to-br from-primary-50 to-white rounded-2xl md:rounded-3xl border border-primary-100 hover:border-primary-300 hover:shadow-2xl hover:shadow-primary-500/10 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="100">
                        <div class="absolute top-6 right-6 w-16 h-16 bg-primary-500/10 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-2xl font-bold text-slate-900 mb-3">Accurate, Timely Billing</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Consistent rate application and policy enforcement across every billing cycle. No more manual errors or calculation discrepancies.
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 text-primary-600 font-semibold">
                            <span>Every. Single. Cycle.</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Guarantee 2 -->
                    <div class="group relative p-10 bg-gradient-to-br from-accent-50 to-white rounded-3xl border border-accent-100 hover:border-accent-300 hover:shadow-2xl hover:shadow-accent-500/10 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="200">
                        <div class="absolute top-6 right-6 w-16 h-16 bg-accent-500/10 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-accent-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-2xl font-bold text-slate-900 mb-3">Faster Payment Collections</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Automated reminders and clear invoices reduce Days Sales Outstanding. Watch your collection rates soar to 95%+.
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 text-accent-600 font-semibold">
                            <span>Reduce DSO by 40%</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Guarantee 3 -->
                    <div class="group relative p-10 bg-gradient-to-br from-purple-50 to-white rounded-3xl border border-purple-100 hover:border-purple-300 hover:shadow-2xl hover:shadow-purple-500/10 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="300">
                        <div class="absolute top-6 right-6 w-16 h-16 bg-purple-500/10 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"></path>
                            </svg>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-2xl font-bold text-slate-900 mb-3">Lower Customer Disputes</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Reading validation, photo evidence, and clear calculation breakdowns build customer trust and reduce disputes to <3%.
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 text-purple-600 font-semibold">
                            <span>80% Fewer Disputes</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Guarantee 4 -->
                    <div class="group relative p-10 bg-gradient-to-br from-orange-50 to-white rounded-3xl border border-orange-100 hover:border-orange-300 hover:shadow-2xl hover:shadow-orange-500/10 transition-all duration-500 hover:-translate-y-2" data-aos="fade-up" data-aos-delay="400">
                        <div class="absolute top-6 right-6 w-16 h-16 bg-orange-500/10 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform">
                            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-2xl font-bold text-slate-900 mb-3">Complete Audit Trail</h3>
                            <p class="text-slate-600 leading-relaxed">
                                Every transaction, correction, reversal, and notification is logged and auditable. Full compliance and accountability.
                            </p>
                        </div>
                        <div class="flex items-center space-x-2 text-orange-600 font-semibold">
                            <span>100% Traceable</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        @include('welcome-sections.how-it-works')
        @include('welcome-sections.features-by-persona')
        @include('welcome-sections.communication')
        @include('welcome-sections.testimonials')
        @include('welcome-sections.footer')

        <!-- Initialize AOS and Mobile Menu -->
        <script>
            // Initialize AOS animations
            AOS.init({
                duration: 800,
                easing: 'ease-out-cubic',
                once: true,
                offset: 100
            });

            // Mobile menu functionality
            document.addEventListener('DOMContentLoaded', function() {
                const mobileMenuButton = document.getElementById('mobile-menu-button');
                const mobileMenu = document.getElementById('mobile-menu');
                
                if (mobileMenuButton && mobileMenu) {
                    // Toggle mobile menu
                    mobileMenuButton.addEventListener('click', function(e) {
                        e.stopPropagation();
                        mobileMenu.classList.toggle('hidden');
                    });

                    // Close mobile menu when clicking on a link
                    const mobileMenuLinks = mobileMenu.querySelectorAll('a');
                    mobileMenuLinks.forEach(function(link) {
                        link.addEventListener('click', function() {
                            mobileMenu.classList.add('hidden');
                        });
                    });

                    // Close mobile menu when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!mobileMenuButton.contains(e.target) && !mobileMenu.contains(e.target)) {
                            mobileMenu.classList.add('hidden');
                        }
                    });
                }
            });
        </script>

    </body>
</html>
