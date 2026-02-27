<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>School Report Card</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" />
    <style>
        html {
            font-family: "Montserrat", sans-serif;
            font-style: normal;
        }

        .header-bg {
            background-color: #0d3d56;
        }

        .theme-bg-primary {
            background-color: #dce3e8;
        }

        .theme-bg-accent {
            background-color: #b8c9d4;
        }

        .header-text-primary {
            color: #ffffff;
        }

        .text-primary {
            color: #0d3d56;
        }

        .border-color {
            border-color: #b8c9d4;
        }

        .watermark-image {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200px;
            opacity: 0.1;
            transform: translate(-50%, -50%);
        }

        @page {
            size: A4;
            margin: 0;
        }

        @media print {

            .header-bg .theme-bg-primary .text-primary .header-text-primary .border-color {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                padding: 0;
                background-color: white;

            }

            .pdf {
                page-break-after: auto;
            }

            .footer {
                position: relative;
                bottom: 0;
                width: 100%;
            }
        }

        .castle-decoration::before,
        .castle-decoration::after {
            content: "";
            position: absolute;
            top: 0;
            width: 40px;
            height: 10px;
            background: white;
        }

        .castle-decoration::before {
            left: 30px;
        }

        .castle-decoration::after {
            left: 80px;
        }
    </style>
</head>

<body class="bg-gray-100 p-5">
    @foreach ($students->chunk(Arr::get($layout, 'column', 1)) as $studentPair)
        <div style="margin-top: {{ Arr::get($layout, 'margin_top', 0) }}mm; page-break-after: always;">
            <div style="display: flex; justify-content: space-between;">
                @foreach ($studentPair as $student)
                    <div style="width: 205mm; min-height: 292mm" class="relative mx-auto bg-white">
                        <img class="watermark-image" src="{{ url(config('config.assets.logo')) }}" alt="Watermark" />

                        <p class="absolute bottom-1 right-2 text-[10px] font-medium">
                            instikit.com
                        </p>
                        <!-- Header -->
                        <div class="w-full p-5">
                            <div
                                class="header-bg header-text-primary castle-decoration relative flex items-center justify-between border-b border-gray-400 px-3 pb-3 pt-3 text-center">
                                <div class="w-35 flex justify-center text-4xl">
                                    <img src="{{ url(config('config.assets.logo')) }}" alt="School Logo"
                                        class="h-full w-full object-cover" />
                                </div>
                                <div class="text-end">
                                    <h1 class="mb-0 text-xl font-bold tracking-widest">
                                        {{ config('config.team.config.name') }}
                                    </h1>
                                    @if (config('config.team.config.title1'))
                                        <h5 class="text-sm font-medium">{{ config('config.team.config.title1') }}</h5>
                                    @endif
                                    @if (config('config.team.config.title2'))
                                        <h5 class="text-sm font-medium">{{ config('config.team.config.title2') }}</h5>
                                    @endif
                                    @if (config('config.team.config.title3'))
                                        <h5 class="text-sm font-medium">{{ config('config.team.config.title3') }}</h5>
                                    @endif

                                    <div class="mt-1 flex flex-col gap-1 text-xs">
                                        @if (config('config.team.config.phone'))
                                            <h6 class="flex items-center justify-end gap-2">
                                                <span><i class="fa-solid fa-envelope"></i></span>
                                                <span class="font-semibold">Phone
                                                    :</span>{{ config('config.team.config.phone') }}
                                            </h6>
                                        @endif
                                        @if (config('config.team.config.email'))
                                            <h6 class="flex items-center justify-end gap-2">
                                                <span><i class="fa-solid fa-envelope"></i></span>
                                                <span class="font-semibold">Email :</span>
                                                {{ config('config.team.config.email') }}
                                            </h6>
                                        @endif
                                        @if (config('config.team.config.website'))
                                            <h6 class="flex items-center justify-end gap-2">
                                                <span><i class="fa-solid fa-globe"></i></span>
                                                <span class="font-semibold">Website :</span>
                                                {{ config('config.team.config.website') }}
                                            </h6>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="flex gap-2 p-5">
                            <!-- Left Section -->
                            <div class="flex flex-1 flex-col gap-4 pb-4">
                                <!-- Subject Grading -->
                                <div class="">
                                    <div class="mb-2 flex justify-between border-b-2 border-gray-300 pb-1">
                                        @foreach ($titles as $title)
                                            @if (Arr::get($title, 'label'))
                                                <h2
                                                    class="text-color-primary text-primary text-center text-sm font-bold">
                                                    {{ Arr::get($title, 'label') }}
                                                </h2>
                                            @endif
                                        @endforeach
                                        <h6 class="text-xs text-gray-600">
                                            <span
                                                class="font-bold">{{ Arr::get($student->summary, 'result_date') }}</span>
                                        </h6>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse">
                                            @foreach ($student->marks as $row)
                                                <tr
                                                    class="@if ($loop->first) theme-bg-accent @endif border-b border-gray-200">
                                                    @foreach ($row as $cell)
                                                        <td
                                                            class="@if (Arr::get($cell, 'type') == 'heading' || Arr::get($cell, 'type') == 'footer') font-semibold @endif px-3 py-1.5 text-xs">
                                                            {{ Arr::get($cell, 'label') }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Section -->
                            <div class="theme-bg-primary flex w-1/4 flex-col items-center p-4">
                                <div
                                    class="mb-5 flex h-40 w-40 items-center justify-center bg-white text-xs text-white">
                                    <img src="{{ $student->photo_url }}" alt="Student Photo"
                                        class="h-full w-full object-cover" />
                                </div>
                                <div
                                    class="text-primary border-color mb-2.5 border-b pb-2.5 text-center text-xs font-bold">
                                    {{ $student->name }}
                                </div>
                                <div class="text-primary mb-4 text-xs font-semibold">
                                    {{ Arr::get(App\Enums\Gender::getDetail($student->gender), 'label') }} |
                                    {{ App\ValueObjects\Cal::date($student->birth_date)?->formatted }}
                                </div>

                                <div class="mb-8 grid w-full grid-cols-2 gap-1.5">
                                    <div class="border-color border-b pb-1.5 text-[10px]">
                                        <span class="text-xs font-semibold">Course</span> <br />
                                        {{ $student->course_name }} {{ $student->batch_name }}
                                    </div>
                                    <div class="border-color border-b pb-1.5 text-[10px]">
                                        <span class="text-xs font-semibold">House</span> <br />
                                        Blue House
                                    </div>
                                    <div class="border-color border-b pb-1.5 text-[10px]">
                                        <span class="text-xs font-semibold">Admin No.</span> <br />
                                        {{ $student->code_number }}
                                    </div>
                                    <div class="border-color border-b pb-1.5 text-[10px]">
                                        <span class="text-xs font-semibold">Roll No.</span> <br />
                                        {{ $student->roll_number }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-gray-300 px-6 py-4">
                            <div class="grid-cols-{{ count(Arr::get($layout, 'signatories')) }} grid gap-10">
                                @foreach (Arr::get($layout, 'signatories') as $index => $signatory)
                                    <div>
                                        <div class="flex flex-col">
                                            <div class="flex h-24 items-center justify-center">
                                                @if (Arr::get($signatory, 'signature'))
                                                    <img src="{{ Arr::get($signatory, 'signature') }}"
                                                        alt="Teacher Signature" class="w-32 object-cover" />
                                                @endif
                                            </div>
                                            <p class="text-primary text-center text-xs font-semibold">
                                                {{ Arr::get($signatory, 'title') }}
                                                @if (Arr::get($signatory, 'name'))
                                                    <span
                                                        style="font-weight: normal">{{ Arr::get($signatory, 'name') }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</body>

</html>
