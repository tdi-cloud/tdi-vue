<template>
    <Head :title="`${requirement.title} Report Form`" />
    <div class="min-h-screen bg-[#f7f9fd] font-[Inter,system-ui,sans-serif] text-[#1a2744] [color-scheme:light]">
        <TheNavbar />

        <!-- Hero -->
        <section class="relative overflow-hidden bg-[#0f1c48] pb-20 pt-32">
            <div class="absolute inset-0 bg-gradient-to-br from-[#0a153c] via-[#132a7a] to-[#1d3fc4] opacity-95"></div>
            <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/5"></div>
            <div class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-sky-400/10"></div>

            <div class="relative mx-auto w-[min(100%-2rem,960px)]">
                <Link
                    :href="route('programs.my-progress', batchId)"
                    class="inline-flex items-center gap-1.5 text-sm text-white/70 no-underline hover:text-white"
                >
                    <ArrowLeft :size="15" /> Back to program
                </Link>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold tracking-wide text-white">{{ requirement.title }}</span>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-white/80">TESDA-OP-AS-01-F07</span>
                    <span
                        v-if="requirement.due_date"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-400/20 px-3 py-1 text-xs text-amber-100"
                    >
                        <CalendarDays :size="12" /> Due {{ formatDate(requirement.due_date) }}
                    </span>
                </div>
                <h1 class="mt-3 text-xl font-extrabold tracking-wide text-white sm:text-2xl">TERMINAL AND RE-ENTRY ACTION PLAN (REAP) REPORT FORM</h1>
                <p class="mt-2 max-w-2xl text-sm text-white/75">
                    {{ program.title }} <span v-if="program.batch_label">· {{ program.batch_label }}</span>
                </p>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-white/85">
                    Fill out the form below. We've already filled in the program details for you — just check them, complete what's missing, and we'll
                    generate the official PDF and submit it for you.
                </p>
            </div>
        </section>

        <main class="report-form relative mx-auto -mt-12 w-[min(100%-2rem,960px)] pb-16">
            <!-- Current submission status -->
            <div
                v-if="submission"
                class="mb-5 flex flex-col gap-3 rounded-2xl border p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                :class="submission.status === 'Rejected' ? 'border-red-200 bg-red-50' : 'border-blue-100 bg-white'"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="submission.status === 'Rejected' ? XCircle : Clock"
                        :size="20"
                        :class="submission.status === 'Rejected' ? 'text-red-600' : 'text-amber-500'"
                        class="mt-0.5 shrink-0"
                    />
                    <div>
                        <p class="text-sm font-bold">
                            {{ submission.status === 'Rejected' ? 'Your report needs revision' : 'Your report is submitted and awaiting review' }}
                        </p>
                        <p v-if="submission.status === 'Rejected' && submission.remarks" class="mt-0.5 text-sm text-red-800">
                            <strong>Reviewer note:</strong> {{ submission.remarks }}
                        </p>
                        <p v-else class="mt-0.5 text-sm text-gray-500">You can still update the form — submitting again replaces the PDF.</p>
                    </div>
                </div>
                <a
                    v-if="submission.file_url"
                    :href="submission.file_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#eef1fc] px-3 py-2 text-xs font-bold text-[#1d3fc4] no-underline hover:bg-[#dde4fb]"
                >
                    <FileText :size="14" /> View current PDF
                </a>
            </div>

            <!-- Stepper -->
            <nav class="rounded-2xl border border-gray-100 bg-white p-3 shadow-sm sm:p-4">
                <ol class="flex items-center">
                    <li v-for="(step, index) in steps" :key="step.key" class="flex flex-1 items-center last:flex-none">
                        <button
                            type="button"
                            class="group flex items-center gap-2.5 rounded-xl px-1.5 py-1 text-left disabled:cursor-not-allowed"
                            :disabled="index > furthestStep"
                            @click="goToStep(index)"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold transition"
                                :class="
                                    index === currentStep
                                        ? 'border-[#1d3fc4] bg-[#1d3fc4] text-white shadow-md shadow-blue-200'
                                        : index < currentStep || index <= furthestStep
                                          ? 'border-emerald-500 bg-emerald-50 text-emerald-600'
                                          : 'border-gray-200 bg-white text-gray-400'
                                "
                            >
                                <Check v-if="index < currentStep" :size="16" />
                                <component :is="step.icon" v-else :size="16" />
                            </span>
                            <span class="hidden md:block">
                                <span class="block text-[11px] font-semibold uppercase tracking-wider text-gray-400">Step {{ index + 1 }}</span>
                                <span class="block text-sm font-bold" :class="index === currentStep ? 'text-[#1d3fc4]' : 'text-gray-700'">
                                    {{ step.label }}
                                </span>
                            </span>
                        </button>
                        <span
                            v-if="index < steps.length - 1"
                            class="mx-2 h-0.5 flex-1 rounded"
                            :class="index < currentStep ? 'bg-emerald-400' : 'bg-gray-200'"
                        ></span>
                    </li>
                </ol>
                <p class="mt-2 text-center text-sm font-bold text-[#1d3fc4] md:hidden">
                    Step {{ currentStep + 1 }} of {{ steps.length }} · {{ steps[currentStep].label }}
                </p>
            </nav>

            <form class="mt-5" @submit.prevent="submit">
                <!-- STEP: Program Description -->
                <section v-show="currentKey === 'program'" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                    <header class="mb-6 flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#1d3fc4]"
                            ><BookOpen :size="20"
                        /></span>
                        <div>
                            <h2 class="text-lg font-extrabold">I. Program Description</h2>
                            <p class="text-sm text-gray-500">
                                Fields marked
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700">Auto-filled</span>
                                come from the program and batch records. Check them and edit if anything is off.
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-5 sm:grid-cols-6">
                        <FieldBlock
                            class="sm:col-span-6"
                            label="Title"
                            required
                            :auto="isAutoFilled('title')"
                            :error="form.errors['program_description.title']"
                        >
                            <input v-model="form.program_description.title" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock
                            class="sm:col-span-4"
                            label="Date / Duration"
                            required
                            :auto="isAutoFilled('date_duration')"
                            :error="form.errors['program_description.date_duration']"
                        >
                            <input
                                v-model="form.program_description.date_duration"
                                type="text"
                                class="field-input"
                                placeholder="e.g. March 3–7, 2026"
                            />
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-2" label="No. of Hours" :auto="isAutoFilled('hours')">
                            <input v-model="form.program_description.hours" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-3" label="Venue" :auto="isAutoFilled('venue')">
                            <input v-model="form.program_description.venue" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-3" label="Conducted by" :auto="isAutoFilled('conducted_by')">
                            <input v-model="form.program_description.conducted_by" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-6" label="Sponsoring Institution" :auto="isAutoFilled('sponsoring_institution')">
                            <input v-model="form.program_description.sponsoring_institution" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock
                            class="sm:col-span-6"
                            label="Designed for"
                            required
                            hint="Who is the program intended for?"
                            :error="form.errors['program_description.designed_for']"
                        >
                            <input
                                v-model="form.program_description.designed_for"
                                type="text"
                                class="field-input"
                                placeholder="e.g. TESDA technical staff and trainers"
                            />
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="suggestion in designedForSuggestions"
                                    :key="suggestion"
                                    type="button"
                                    class="rounded-full border border-gray-200 px-2.5 py-1 text-xs text-gray-600 transition hover:border-[#1d3fc4] hover:bg-blue-50 hover:text-[#1d3fc4]"
                                    @click="form.program_description.designed_for = suggestion"
                                >
                                    {{ suggestion }}
                                </button>
                            </div>
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-6" label="Objectives" :auto="isAutoFilled('objectives')">
                            <textarea v-model="form.program_description.objectives" rows="3" class="field-input"></textarea>
                        </FieldBlock>
                        <FieldBlock
                            class="sm:col-span-6"
                            label="Methodology"
                            :auto="isAutoFilled('methodology')"
                            hint="e.g. lectures, workshops, field visits"
                        >
                            <input v-model="form.program_description.methodology" type="text" class="field-input" />
                        </FieldBlock>
                        <FieldBlock class="sm:col-span-3" label="Course Outline" :auto="isAutoFilled('course_outline')" hint="One topic per line">
                            <textarea v-model="form.program_description.course_outline" rows="5" class="field-input"></textarea>
                        </FieldBlock>
                        <FieldBlock
                            class="sm:col-span-3"
                            label="Resource Speakers / Facilitators"
                            :auto="isAutoFilled('resource_speakers')"
                            hint="One person per line"
                        >
                            <textarea v-model="form.program_description.resource_speakers" rows="5" class="field-input"></textarea>
                        </FieldBlock>
                    </div>
                </section>

                <!-- STEP: Re-Entry Action Plan (required for REAP, optional for TREAP) -->
                <section v-show="currentKey === 'reap'" class="space-y-5">
                    <!-- TREAP: ask whether to fill out Part II -->
                    <div v-if="!reentryPlanRequired" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                        <header class="mb-5 flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                                <Rocket :size="20" />
                            </span>
                            <div>
                                <h2 class="text-lg font-extrabold">II. Re-Entry Action Plan (REAP)</h2>
                                <p class="text-sm text-gray-500">Part II is optional for a Terminal Report. Would you like to fill it out?</p>
                            </div>
                        </header>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition"
                                :class="
                                    fillReentryPlan === true
                                        ? 'border-violet-500 bg-violet-50 shadow-md shadow-violet-100'
                                        : 'border-gray-200 hover:border-violet-300 hover:bg-violet-50/40'
                                "
                                @click="chooseReentryPlan(true)"
                            >
                                <span
                                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                    :class="fillReentryPlan === true ? 'border-violet-600 bg-violet-600 text-white' : 'border-gray-300'"
                                >
                                    <Check v-if="fillReentryPlan === true" :size="12" />
                                </span>
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">Yes, I'll fill it out</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">
                                        Share what you learned and your action plan for applying it at work.
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition"
                                :class="
                                    fillReentryPlan === false
                                        ? 'border-gray-500 bg-gray-50 shadow-md shadow-gray-100'
                                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                "
                                @click="chooseReentryPlan(false)"
                            >
                                <span
                                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2"
                                    :class="fillReentryPlan === false ? 'border-gray-600 bg-gray-600 text-white' : 'border-gray-300'"
                                >
                                    <Check v-if="fillReentryPlan === false" :size="12" />
                                </span>
                                <span>
                                    <span class="block text-sm font-bold text-gray-900">No, skip this part</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">Part II will still appear on the PDF, marked "N/A".</span>
                                </span>
                            </button>
                        </div>

                        <div v-if="fillReentryPlan === false" class="mt-4 flex items-start gap-3 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">
                            <Info :size="18" class="mt-0.5 shrink-0 text-gray-500" />
                            <p>All Part II fields and the action plan table will show <strong>N/A</strong> on your generated PDF.</p>
                        </div>
                    </div>

                    <div v-if="fillReentryPlan" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                        <header v-if="reentryPlanRequired" class="mb-6 flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600"
                                ><Rocket :size="20"
                            /></span>
                            <div>
                                <h2 class="text-lg font-extrabold">II. Re-Entry Action Plan (REAP)</h2>
                                <p class="text-sm text-gray-500">Share what you learned and how you'll apply it back in your office.</p>
                            </div>
                        </header>

                        <div class="space-y-5">
                            <FieldBlock
                                label="Competency/ies Achieved / Learning Gained"
                                number="1"
                                required
                                hint="What new skills or knowledge did you gain from the program?"
                                :error="form.errors.competencies_gained"
                            >
                                <textarea v-model="form.competencies_gained" rows="4" class="field-input"></textarea>
                            </FieldBlock>
                            <FieldBlock
                                label="Summary of the REAP"
                                number="2"
                                required
                                hint="In a few sentences, what do you plan to do once you're back at work?"
                                :error="form.errors.reap_summary"
                            >
                                <textarea v-model="form.reap_summary" rows="4" class="field-input"></textarea>
                            </FieldBlock>
                            <FieldBlock
                                label="Objectives"
                                number="3"
                                required
                                hint="What does your action plan aim to achieve?"
                                :error="form.errors.reap_objectives"
                            >
                                <textarea v-model="form.reap_objectives" rows="3" class="field-input"></textarea>
                            </FieldBlock>
                        </div>
                    </div>

                    <div v-if="fillReentryPlan" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                        <header class="mb-5 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-extrabold">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700"
                                        >4</span
                                    >
                                    Details of the Action Plan <span class="text-red-500">*</span>
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">List each activity you'll carry out. Add as many as you need.</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                {{ form.action_plan.length }} {{ form.action_plan.length === 1 ? 'activity' : 'activities' }}
                            </span>
                        </header>

                        <p v-if="form.errors.action_plan" class="mb-3 text-sm text-red-600">{{ form.errors.action_plan }}</p>

                        <TransitionGroup tag="div" name="plan" class="space-y-4">
                            <div
                                v-for="(row, index) in form.action_plan"
                                :key="row.key"
                                class="relative rounded-xl border border-gray-200 bg-gray-50/60 p-4 sm:p-5"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-violet-700">Activity {{ index + 1 }}</span>
                                    <button
                                        v-if="form.action_plan.length > 1"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-red-50 hover:text-red-600"
                                        @click="removeActivity(index)"
                                    >
                                        <Trash2 :size="13" /> Remove
                                    </button>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <FieldBlock label="Activity" required :error="form.errors[`action_plan.${index}.activity`]">
                                        <textarea
                                            v-model="row.activity"
                                            rows="2"
                                            class="field-input"
                                            placeholder="e.g. Echo the training to unit staff"
                                        ></textarea>
                                    </FieldBlock>
                                    <FieldBlock label="Expected Output" required :error="form.errors[`action_plan.${index}.expected_output`]">
                                        <textarea
                                            v-model="row.expected_output"
                                            rows="2"
                                            class="field-input"
                                            placeholder="e.g. 10 staff oriented"
                                        ></textarea>
                                    </FieldBlock>
                                    <FieldBlock label="Schedule of Implementation" required :error="form.errors[`action_plan.${index}.schedule`]">
                                        <input v-model="row.schedule" type="text" class="field-input" placeholder="e.g. 2nd quarter 2026" />
                                    </FieldBlock>
                                    <FieldBlock label="Budget">
                                        <input v-model="row.budget" type="text" class="field-input" placeholder="e.g. ₱5,000 or None" />
                                    </FieldBlock>
                                    <FieldBlock class="sm:col-span-2" label="Remarks">
                                        <input v-model="row.remarks" type="text" class="field-input" />
                                    </FieldBlock>
                                </div>
                            </div>
                        </TransitionGroup>

                        <button
                            type="button"
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-violet-200 py-3 text-sm font-bold text-violet-700 transition hover:border-violet-400 hover:bg-violet-50 disabled:opacity-50"
                            :disabled="form.action_plan.length >= 20"
                            @click="addActivity"
                        >
                            <Plus :size="16" /> Add another activity
                        </button>
                    </div>
                </section>

                <!-- STEP: Sign-off -->
                <section v-show="currentKey === 'signoff'" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                    <header class="mb-6 flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"
                            ><PenLine :size="20"
                        /></span>
                        <div>
                            <h2 class="text-lg font-extrabold">Sign-off</h2>
                            <p class="text-sm text-gray-500">These names are printed on the form. The signature lines are left blank for signing.</p>
                        </div>
                    </header>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 p-5">
                            <h3 class="mb-1 text-base font-bold text-gray-900">Prepared by</h3>
                            <p class="mb-4 text-sm text-gray-500">That's you — filled in from your employee record.</p>
                            <div class="space-y-4">
                                <FieldBlock label="Name" required :error="form.errors['prepared_by.name']">
                                    <input v-model="form.prepared_by.name" type="text" class="field-input" />
                                </FieldBlock>
                                <FieldBlock label="Position" required :error="form.errors['prepared_by.position']">
                                    <input v-model="form.prepared_by.position" type="text" class="field-input" />
                                </FieldBlock>
                                <FieldBlock label="Office">
                                    <input v-model="form.prepared_by.office" type="text" class="field-input" />
                                </FieldBlock>
                                <FieldBlock label="Date" required :error="form.errors['prepared_by.date']">
                                    <input v-model="form.prepared_by.date" type="date" class="field-input" />
                                </FieldBlock>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 p-5">
                            <SignatoryPicker
                                v-model="approver"
                                title="Discussed with and Approved by"
                                description="Search for your supervisor or the official who approves your report."
                                position-label="Position (as shown on the form)"
                                :error="form.errors['approved_by.name'] || form.errors['approved_by.position']"
                            />
                            <div v-if="approver" class="mt-4 space-y-4">
                                <FieldBlock label="Office">
                                    <input v-model="approver.office" type="text" class="field-input" />
                                </FieldBlock>
                                <FieldBlock label="Date approved" hint="Optional — leave blank if not yet approved">
                                    <input v-model="approvedDate" type="date" class="field-input" />
                                </FieldBlock>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- STEP: Review -->
                <section v-show="currentKey === 'review'" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-7">
                    <header class="mb-6 flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                            ><ClipboardCheck :size="20"
                        /></span>
                        <div>
                            <h2 class="text-lg font-extrabold">Review &amp; Submit</h2>
                            <p class="text-sm text-gray-500">Here's how your form will read. Click a section's Edit link to change anything.</p>
                        </div>
                    </header>

                    <!-- Paper-style preview -->
                    <div class="rounded-xl border border-gray-200 bg-[#fdfdfb] p-5 shadow-inner sm:p-8">
                        <p class="text-right text-[11px] leading-tight text-gray-500">TESDA-OP-AS-01-F07<br />Rev. No. 00 – 03/01/17</p>
                        <p class="mt-3 text-center text-sm font-bold">Technical Education and Skills Development Authority</p>
                        <p class="text-center text-sm font-bold">TERMINAL AND RE-ENTRY ACTION PLAN (REAP) REPORT FORM</p>

                        <div class="mt-6 flex items-center justify-between">
                            <h3 class="text-sm font-bold">I. Program Description</h3>
                            <button type="button" class="text-xs font-semibold text-[#1d3fc4] hover:underline" @click="goToKey('program')">
                                Edit
                            </button>
                        </div>
                        <dl class="mt-2 divide-y divide-gray-100 text-sm">
                            <div v-for="item in descriptionPreview" :key="item.label" class="grid gap-1 py-2 sm:grid-cols-[200px_1fr]">
                                <dt class="font-semibold text-gray-600">{{ item.label }}</dt>
                                <dd class="whitespace-pre-line" :class="item.value ? 'text-gray-900' : 'italic text-gray-400'">
                                    {{ item.value || 'Not provided' }}
                                </dd>
                            </div>
                        </dl>

                        <template>
                            <div class="mt-6 flex items-center justify-between">
                                <h3 class="text-sm font-bold">
                                    II. Re-Entry Action Plan (REAP)
                                    <span
                                        v-if="!fillReentryPlan"
                                        class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-semibold text-gray-500"
                                    >
                                        Skipped — shown as N/A
                                    </span>
                                </h3>
                                <button type="button" class="text-xs font-semibold text-[#1d3fc4] hover:underline" @click="goToKey('reap')">
                                    Edit
                                </button>
                            </div>
                            <dl class="mt-2 divide-y divide-gray-100 text-sm">
                                <div v-for="item in reapPreview" :key="item.label" class="grid gap-1 py-2 sm:grid-cols-[200px_1fr]">
                                    <dt class="font-semibold text-gray-600">{{ item.label }}</dt>
                                    <dd class="whitespace-pre-line text-gray-900">{{ item.value }}</dd>
                                </div>
                            </dl>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full min-w-[560px] border-collapse text-left text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 text-gray-700">
                                            <th class="border border-gray-200 px-2 py-1.5">Activities</th>
                                            <th class="border border-gray-200 px-2 py-1.5">Expected Output</th>
                                            <th class="border border-gray-200 px-2 py-1.5">Schedule</th>
                                            <th class="border border-gray-200 px-2 py-1.5">Budget</th>
                                            <th class="border border-gray-200 px-2 py-1.5">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!fillReentryPlan" class="text-gray-500">
                                            <td v-for="column in 5" :key="column" class="border border-gray-200 px-2 py-1.5">N/A</td>
                                        </tr>
                                        <tr v-for="row in fillReentryPlan ? form.action_plan : []" :key="row.key">
                                            <td class="whitespace-pre-line border border-gray-200 px-2 py-1.5">{{ row.activity }}</td>
                                            <td class="whitespace-pre-line border border-gray-200 px-2 py-1.5">{{ row.expected_output }}</td>
                                            <td class="border border-gray-200 px-2 py-1.5">{{ row.schedule }}</td>
                                            <td class="border border-gray-200 px-2 py-1.5">{{ row.budget }}</td>
                                            <td class="border border-gray-200 px-2 py-1.5">{{ row.remarks }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div class="mt-6 flex items-center justify-between">
                            <h3 class="text-sm font-bold">Sign-off</h3>
                            <button type="button" class="text-xs font-semibold text-[#1d3fc4] hover:underline" @click="goToKey('signoff')">
                                Edit
                            </button>
                        </div>
                        <div class="mt-2 grid gap-3 text-sm sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Prepared by</p>
                                <p class="mt-1 font-bold uppercase">{{ form.prepared_by.name }}</p>
                                <p class="text-gray-600">
                                    {{ form.prepared_by.position }}<span v-if="form.prepared_by.office"> · {{ form.prepared_by.office }}</span>
                                </p>
                                <p class="text-gray-500">{{ formatDate(form.prepared_by.date) }}</p>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Discussed with and Approved by</p>
                                <template v-if="approver">
                                    <p class="mt-1 font-bold uppercase">{{ approver.name }}</p>
                                    <p class="text-gray-600">
                                        {{ approver.position }}<span v-if="approver.office"> · {{ approver.office }}</span>
                                    </p>
                                    <p class="text-gray-500">{{ approvedDate ? formatDate(approvedDate) : 'Date left blank' }}</p>
                                </template>
                                <p v-else class="mt-1 italic text-gray-400">Not selected</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-start gap-3 rounded-xl bg-blue-50 p-4 text-sm text-[#1a2744]">
                        <Info :size="18" class="mt-0.5 shrink-0 text-[#1d3fc4]" />
                        <p>
                            Clicking <strong>Generate &amp; Submit</strong> creates the PDF and submits it as your {{ requirement.title }} requirement
                            for review. You can come back and update it until it's approved.
                        </p>
                    </div>

                    <p v-if="hasServerErrors" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                        Some fields need your attention. Use the Edit links above to fix them.
                    </p>
                </section>

                <!-- Navigation -->
                <div class="mt-5 flex items-center justify-between gap-3">
                    <button
                        v-if="currentStep > 0"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50"
                        @click="goToStep(currentStep - 1)"
                    >
                        <ArrowLeft :size="16" /> Back
                    </button>
                    <span v-else></span>

                    <button
                        v-if="currentKey !== 'review'"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#1d3fc4] px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-200 hover:bg-[#1535a8]"
                        @click="next"
                    >
                        Continue <ArrowRight :size="16" />
                    </button>
                    <button
                        v-else
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-200 hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Loader2 v-if="form.processing" :size="16" class="animate-spin" />
                        <FileCheck2 v-else :size="16" />
                        {{ form.processing ? 'Generating PDF…' : 'Generate & Submit' }}
                    </button>
                </div>
                <p v-if="stepError" class="mt-3 text-right text-sm font-semibold text-red-600">{{ stepError }}</p>
            </form>
        </main>

        <TheFooter />
    </div>
</template>

<script setup lang="ts">
import SignatoryPicker from '@/pages/Tna/SignatoryPicker.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CalendarDays,
    Check,
    ClipboardCheck,
    Clock,
    FileCheck2,
    FileText,
    Info,
    Loader2,
    PenLine,
    Plus,
    Rocket,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, defineComponent, h, ref } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

interface Signatory {
    empcode?: string | null;
    name: string;
    position: string | null;
    office: string | null;
    date?: string | null;
}

interface ActionPlanRow {
    key: number;
    activity: string;
    expected_output: string;
    schedule: string;
    budget: string;
    remarks: string;
}

const props = defineProps<{
    batchId: number;
    requirement: { id: number; title: string; name: string; due_date: string | null };
    program: { title: string | null; batch_label: string | null };
    reentryPlanRequired: boolean;
    autoFilled: Record<string, string | null>;
    report: Record<string, any> | null;
    defaultPreparedBy: Signatory;
    submission: { status: string; remarks: string | null; file_url: string | null } | null;
}>();

/* ---- Small labelled field wrapper ---- */
const FieldBlock = defineComponent({
    props: {
        label: { type: String, required: true },
        required: Boolean,
        auto: Boolean,
        hint: { type: String, default: '' },
        error: { type: String, default: '' },
        number: { type: String, default: '' },
    },
    setup(fieldProps, { slots }) {
        return () =>
            h('div', [
                h('div', { class: 'mb-1.5 flex flex-wrap items-center gap-2' }, [
                    fieldProps.number
                        ? h(
                              'span',
                              { class: 'flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700' },
                              fieldProps.number,
                          )
                        : null,
                    h('label', { class: 'text-sm font-bold text-gray-800' }, [
                        fieldProps.label,
                        fieldProps.required ? h('span', { class: 'ml-0.5 text-red-500' }, '*') : null,
                    ]),
                    fieldProps.auto
                        ? h('span', { class: 'rounded bg-emerald-50 px-1.5 py-0.5 text-[11px] font-bold text-emerald-700' }, 'Auto-filled')
                        : null,
                ]),
                fieldProps.hint ? h('p', { class: 'mb-1.5 text-xs text-gray-500' }, fieldProps.hint) : null,
                slots.default?.(),
                fieldProps.error ? h('p', { class: 'mt-1 text-xs font-medium text-red-600' }, fieldProps.error) : null,
            ]);
    },
});

/* ---- Form state ---- */
let rowKey = 0;
const blankActivity = (): ActionPlanRow => ({ key: ++rowKey, activity: '', expected_output: '', schedule: '', budget: '', remarks: '' });

const savedPlan = (props.report?.action_plan ?? []) as Omit<ActionPlanRow, 'key'>[];

const form = useForm({
    program_description: { ...props.autoFilled, ...(props.report?.program_description ?? {}) } as Record<string, string | null>,
    competencies_gained: (props.report?.competencies_gained ?? '') as string,
    reap_summary: (props.report?.reap_summary ?? '') as string,
    reap_objectives: (props.report?.reap_objectives ?? '') as string,
    action_plan: savedPlan.length ? savedPlan.map((row) => ({ ...blankActivity(), ...row })) : [blankActivity()],
    prepared_by: { ...props.defaultPreparedBy, ...(props.report?.prepared_by ?? {}) } as Signatory,
    approved_by: null as Signatory | null,
});

const savedApprover = props.report?.approved_by as Signatory | undefined;
const approver = ref<Signatory | null>(savedApprover ? { ...savedApprover } : null);
const approvedDate = ref<string>(savedApprover?.date ?? '');

// Part II: always filled out for REAP; for TREAP the participant chooses (null = not chosen yet).
const fillReentryPlan = ref<boolean | null>(props.reentryPlanRequired ? true : props.report ? !!props.report.has_reentry_plan : null);

function chooseReentryPlan(fill: boolean) {
    fillReentryPlan.value = fill;
    stepError.value = '';
}

const isAutoFilled = (field: string) => !!props.autoFilled[field] && form.program_description[field] === props.autoFilled[field];

const designedForSuggestions = ['TESDA employees', 'TESDA trainers and assessors', 'Supervisors and managers', 'Administrative staff'];

function addActivity() {
    form.action_plan.push(blankActivity());
}

function removeActivity(index: number) {
    form.action_plan.splice(index, 1);
}

/* ---- Steps ---- */
const steps = computed(() => [
    { key: 'program', label: 'Program Details', icon: BookOpen },
    { key: 'reap', label: props.reentryPlanRequired ? 'Re-Entry Plan' : 'Re-Entry Plan (optional)', icon: Rocket },
    { key: 'signoff', label: 'Sign-off', icon: PenLine },
    { key: 'review', label: 'Review & Submit', icon: ClipboardCheck },
]);

const currentStep = ref(0);
const furthestStep = ref(props.report ? steps.value.length - 1 : 0);
const currentKey = computed(() => steps.value[currentStep.value].key);
const stepError = ref('');

const filled = (value: string | null | undefined) => !!value && value.trim() !== '';

function stepProblem(key: string): string {
    if (key === 'program') {
        const pd = form.program_description;
        if (!filled(pd.title) || !filled(pd.date_duration) || !filled(pd.designed_for)) {
            return 'Please fill in the Title, Date/Duration and "Designed for" fields.';
        }
    }
    if (key === 'reap') {
        if (fillReentryPlan.value === null) {
            return 'Please choose whether to fill out Part II.';
        }
        if (!fillReentryPlan.value) {
            return '';
        }
        if (!filled(form.competencies_gained) || !filled(form.reap_summary) || !filled(form.reap_objectives)) {
            return 'Please answer items 1 to 3 of the Re-Entry Action Plan.';
        }
        if (form.action_plan.some((row) => !filled(row.activity) || !filled(row.expected_output) || !filled(row.schedule))) {
            return 'Each activity needs an activity, expected output and schedule.';
        }
    }
    if (key === 'signoff') {
        if (!filled(form.prepared_by.name) || !filled(form.prepared_by.position) || !filled(form.prepared_by.date)) {
            return 'Please complete your name, position and date.';
        }
        if (!approver.value || !filled(approver.value.position)) {
            return 'Please select who discussed and approved your report.';
        }
    }
    return '';
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep(index: number) {
    if (index > furthestStep.value) {
        return;
    }
    stepError.value = '';
    currentStep.value = index;
    scrollToTop();
}

function goToKey(key: string) {
    goToStep(steps.value.findIndex((step) => step.key === key));
}

function next() {
    const problem = stepProblem(currentKey.value);
    if (problem) {
        stepError.value = problem;
        return;
    }
    stepError.value = '';
    currentStep.value++;
    furthestStep.value = Math.max(furthestStep.value, currentStep.value);
    scrollToTop();
}

/* ---- Review ---- */
const descriptionPreview = computed(() => {
    const pd = form.program_description;
    return [
        { label: 'Title', value: pd.title },
        { label: 'Date/Duration', value: pd.date_duration },
        { label: 'No. of Hours', value: pd.hours },
        { label: 'Venue', value: pd.venue },
        { label: 'Conducted by', value: pd.conducted_by },
        { label: 'Sponsoring Institution', value: pd.sponsoring_institution },
        { label: 'Designed for', value: pd.designed_for },
        { label: 'Objectives', value: pd.objectives },
        { label: 'Methodology', value: pd.methodology },
        { label: 'Course Outline', value: pd.course_outline },
        { label: 'Resource Speakers / Facilitators', value: pd.resource_speakers },
    ];
});

const reapPreview = computed(() => [
    { label: 'Competency/ies Achieved / Learning Gained', value: fillReentryPlan.value ? form.competencies_gained : 'N/A' },
    { label: 'Summary of the REAP', value: fillReentryPlan.value ? form.reap_summary : 'N/A' },
    { label: 'Objectives', value: fillReentryPlan.value ? form.reap_objectives : 'N/A' },
]);

const hasServerErrors = computed(() => Object.keys(form.errors).length > 0);

function submit() {
    for (const step of steps.value) {
        const problem = stepProblem(step.key);
        if (problem) {
            stepError.value = problem;
            return;
        }
    }

    form.transform((data) => ({
        ...data,
        include_reentry_plan: !!fillReentryPlan.value,
        action_plan: fillReentryPlan.value
            ? data.action_plan.map((row) => ({
                  activity: row.activity,
                  expected_output: row.expected_output,
                  schedule: row.schedule,
                  budget: row.budget,
                  remarks: row.remarks,
              }))
            : [],
        approved_by: approver.value ? { ...approver.value, date: approvedDate.value || null } : null,
    })).put(route('programs.terminal-report.update', { batch: props.batchId, requirement: props.requirement.id }), {
        preserveScroll: true,
    });
}

function formatDate(value: string | null | undefined) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}
</script>

<style scoped>
/* This page is designed light-only. The global input styles in app.css follow the
   theme variables, so pin them to the light theme inside the form area; otherwise
   inputs turn black in dark mode. */
.report-form {
    --background: 0 0% 100%;
    --foreground: 0 0% 3.9%;
    --muted-foreground: 0 0% 45.1%;
    --border: 0 0% 92.8%;
    --input: 0 0% 89.8%;
    color-scheme: light;
}
.report-form input[type='date']::-webkit-calendar-picker-indicator {
    filter: none;
}
.field-input {
    width: 100%;
    border-radius: 0.6rem;
    border: 1px solid #d1d5db;
    background: #fff;
    padding: 0.6rem 0.85rem;
    font-size: 0.875rem;
    color: #111827;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
}
.field-input:focus {
    outline: none;
    border-color: #1d3fc4;
    box-shadow: 0 0 0 3px rgba(29, 63, 196, 0.15);
}
textarea.field-input {
    resize: vertical;
    line-height: 1.5;
}
.plan-enter-active,
.plan-leave-active {
    transition: all 0.25s ease;
}
.plan-enter-from,
.plan-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
