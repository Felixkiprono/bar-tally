<!-- CTA Section -->
<section class="py-24 bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 relative overflow-hidden">
    <!-- Background Elements -->
    <div class="absolute inset-0 opacity-20">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-white rounded-full mix-blend-lighten filter blur-3xl animate-float"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-accent-400 rounded-full mix-blend-lighten filter blur-3xl animate-float-delayed"></div>
    </div>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10" data-aos="fade-up">
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-grotesk font-bold text-white mb-6">
            Ready to Control Your Bar?
        </h2>
        <p class="text-xl text-primary-100 mb-10 max-w-2xl mx-auto">
            Join 50+ bars already tracking stock, sales, and variance with confidence
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @auth
                <a href="/{{ config('filament.tenant.path') }}"
                   class="group px-10 py-5 bg-white text-primary-700 text-lg font-bold rounded-2xl hover:bg-primary-50 transition-all duration-300 shadow-2xl hover:shadow-white/30 hover:-translate-y-1 inline-flex items-center">
                    Go to Dashboard
                    <svg class="ml-2 w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
            @else
                <a href="/{{ config('filament.tenant.path') }}/login"
                   class="group px-10 py-5 bg-white text-primary-700 text-lg font-bold rounded-2xl hover:bg-primary-50 transition-all duration-300 shadow-2xl hover:shadow-white/30 hover:-translate-y-1 inline-flex items-center">
                    Get Started Today
                    <svg class="ml-2 w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
                <a href="#how-it-works"
                   class="px-10 py-5 bg-white/10 backdrop-blur-sm text-white text-lg font-bold rounded-2xl hover:bg-white/20 transition-all duration-300 border-2 border-white/30 inline-flex items-center">
                    Learn More
                </a>
            @endauth
        </div>

        <!-- Trust Indicators -->
        <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8 text-white/90">
            <div>
                <div class="text-4xl font-bold mb-2">50+</div>
                <div class="text-primary-200">Bars & Clubs</div>
            </div>
            <div>
                <div class="text-4xl font-bold mb-2">100%</div>
                <div class="text-primary-200">Inventory Accuracy</div>
            </div>
            <div>
                <div class="text-4xl font-bold mb-2">$1K+</div>
                <div class="text-primary-200">Avg. Savings/Month</div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-slate-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid md:grid-cols-4 gap-8 mb-12">

            <!-- Brand Column -->
            <div class="md:col-span-2">
                <div class="flex items-center space-x-3 mb-6">
                    <!-- <img src="{{ asset('logo.png') }}" alt="BarMetriks" class="h-12 w-auto filter brightness-0 invert"> -->
                    <div class="flex flex-col">
                        <span class="text-2xl font-grotesk font-bold leading-tight">BarMetriks</span>
                        <span class="text-sm text-slate-500 -mt-1">Bar Inventory & Sales</span>
                    </div>
                </div>
                <p class="text-slate-400 leading-relaxed mb-6 max-w-md">
                    Simple, powerful inventory and sales tracking for bars and clubs. Track stock, log sales, spot variance in real-time.
                </p>
                <div class="flex space-x-4">
                    <!-- Social Links -->
                    <a href="#" class="w-10 h-10 bg-slate-800 hover:bg-primary-600 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-slate-800 hover:bg-primary-600 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                        </svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-slate-800 hover:bg-primary-600 rounded-full flex items-center justify-center transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Product Column -->
            <div>
                <h3 class="font-bold text-white mb-4">Product</h3>
                <ul class="space-y-2">
                    <li><a href="#features" class="text-slate-400 hover:text-white transition-colors">Features</a></li>
                    <li><a href="#how-it-works" class="text-slate-400 hover:text-white transition-colors">How It Works</a></li>
                    <li><a href="#benefits" class="text-slate-400 hover:text-white transition-colors">Benefits</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition-colors">Pricing</a></li>
                </ul>
            </div>

            <!-- Company Column -->
            <div>
                <h3 class="font-bold text-white mb-4">Company</h3>
                <ul class="space-y-2">
                    <li><a href="#" class="text-slate-400 hover:text-white transition-colors">About Us</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition-colors">Contact</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition-colors">Support</a></li>
                    <li><a href="#" class="text-slate-400 hover:text-white transition-colors">Privacy Policy</a></li>
                </ul>
            </div>

        </div>

        <!-- Bottom Bar -->
        <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-slate-500 text-sm">
                &copy; {{ date('Y') }} BarMetriks. All rights reserved.
            </p>
            <p class="text-slate-500 text-sm mt-4 md:mt-0">
                Made with ❤️ for bar managers and owners
            </p>
        </div>
    </div>
</footer>

