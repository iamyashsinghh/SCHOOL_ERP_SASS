<div x-data="programsData()" class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

        <!-- School Tabs -->
        <div class="mb-8">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 overflow-x-auto">
                    @foreach ($programTypes as $schoolName => $schoolData)
                        <button @click="activeSchool = '{{ $schoolName }}', activeType = 0"
                            :class="activeSchool === '{{ $schoolName }}' ?
                                'border-site-primary text-site-primary' :
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-colors duration-200">
                            {{ $schoolName }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </div>

        <!-- Program Type Buttons -->
        <div class="mb-8">
            <div class="flex flex-wrap justify-center gap-2">
                <template x-for="(type, index) in getCurrentSchoolData()" :key="index">
                    <button @click="activeType = index"
                        :class="activeType === index ?
                            'bg-site-primary text-white shadow-lg' :
                            'bg-white text-gray-700 hover:bg-site-light-primary hover:text-white'"
                        class="focus:ring-site-primary rounded-full border border-gray-200 px-6 py-3 font-medium transition-all duration-200 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2">
                        <div class="flex items-center space-x-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                            <span x-text="type.name"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Program Type Description and Programs -->
        <template x-for="(type, typeIndex) in getCurrentSchoolData()" :key="typeIndex">
            <div x-show="activeType === typeIndex" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform translate-y-4"
                x-transition:enter-end="opacity-100 transform translate-y-0" class="space-y-6">

                <div class="mb-8 rounded-2xl border bg-white p-6 shadow-sm">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="bg-site-primary flex h-12 w-12 items-center justify-center rounded-xl">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9-5-9-5-9 5 9 5z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h2 class="mb-2 text-2xl font-bold text-gray-900" x-text="type.name"></h2>
                            <p class="text-gray-600" x-text="type.description"></p>
                            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-500">
                                <span class="flex items-center">
                                    <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                    </svg>
                                    <span x-text="type.programs.length + ' Programs Available'"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Programs Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <template x-for="program in type.programs" :key="program.id">
                        <div
                            class="group flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-300 hover:shadow-lg">
                            <div class="from-site-primary to-site-dark-primary bg-gradient-to-r p-6 text-white">
                                <h3 class="mb-2 flex min-h-[3.5rem] items-start text-xl font-semibold"
                                    x-text="program.name"></h3>
                                <div class="flex items-center justify-between text-white">
                                    <span class="flex items-center text-sm">
                                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span x-text="program.duration"></span>
                                    </span>
                                    <span class="rounded-full bg-white/20 px-2 py-1 text-xs font-medium">
                                        Active
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col p-6">
                                <div class="flex-1">
                                    <div class="mb-4">
                                        <h4 class="mb-2 flex items-center text-sm font-semibold text-gray-900">
                                            <svg class="text-site-primary mr-1 h-4 w-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                            </svg>
                                            Eligibility
                                        </h4>
                                        <p class="text-sm leading-relaxed text-gray-600" x-text="program.eligibility">
                                        </p>
                                    </div>

                                    <div class="mb-4">
                                        <h4 class="mb-2 flex items-center text-sm font-semibold text-gray-900">
                                            <svg class="text-site-primary mr-1 h-4 w-4" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                            </svg>
                                            Benefits
                                        </h4>
                                        <p class="text-sm leading-relaxed text-gray-600" x-text="program.benefits">
                                        </p>
                                    </div>
                                </div>

                                <div class="flex space-x-2">
                                    <a href="/app/online-registration"
                                        class="bg-site-primary hover:bg-site-dark-primary flex-1 rounded-lg px-4 py-2 text-sm font-medium text-white transition-colors duration-200">
                                        Apply Now
                                    </a>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- No Programs Available -->
        <div x-show="getCurrentPrograms().length === 0" class="py-12 text-center">
            <svg class="mx-auto mb-4 h-16 w-16 text-gray-300" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <h3 class="mb-2 text-lg font-medium text-gray-900">No Programs Available</h3>
            <p class="text-gray-500">Programs for this category will be available soon.</p>
        </div>
    </div>
</div>

<script>
    function programsData() {
        return {
            activeSchool: '{{ array_key_first($programTypes) }}', // Initialize with first school
            activeType: 0,
            schoolsData: @json($programTypes),

            getCurrentSchoolData() {
                return this.schoolsData[this.activeSchool] || [];
            },

            getCurrentPrograms() {
                const schoolData = this.getCurrentSchoolData();
                return schoolData[this.activeType]?.programs || [];
            }
        }
    }
</script>
