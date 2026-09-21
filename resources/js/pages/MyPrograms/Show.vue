<template>
    <Head :title="program.program_title" />
    <div class="progress-page">
        <TheNavbar />

        <!-- Hero header -->
        <section class="ph">
            <div class="ph__bg" :style="{ backgroundImage: program.cover_image ? `url(${program.cover_image})` : 'none' }"></div>
            <div class="ph__overlay"></div>
            <div class="ph__inner">
                <Link href="/#my-programs" class="ph__back"><ArrowLeft :size="15" /> Back to My Programs</Link>

                <div class="ph__top">
                    <div class="ph__title-col">
                        <div class="ph__badges">
                            <span class="ph__badge"><CalendarDays :size="12" /> {{ program.year }}</span>
                            <span class="ph__badge ph__badge--muted">{{ program.batch_label }}</span>
                        </div>
                        <h1>{{ program.program_title }}</h1>
                        <div class="ph__meta">
                            <span v-if="program.venue"><MapPin :size="14" /> {{ program.venue }}</span>
                            <span v-if="program.modality"><Layers :size="14" /> {{ program.modality }}</span>
                            <span><CalendarDays :size="14" /> {{ formatDate(program.date_start) }} – {{ formatDate(program.date_end) }}</span>
                        </div>
                    </div>

                    <div class="ph__progress-card">
                        <div class="ph__status" :class="`tone-${heroStatus.tone}`">
                            <component :is="heroStatus.icon" :size="15" /> {{ heroStatus.label.toUpperCase() }}
                        </div>
                        <p class="ph__progress-line">
                            {{ program.hours_completed }} of {{ program.total_hours || '—' }} learning hours completed
                        </p>
                        <div class="ph__progress-bar">
                            <div class="ph__progress-fill" :style="{ width: hoursPercent + '%' }"></div>
                        </div>
                        <span class="ph__progress-pct">{{ hoursPercent }}%</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="dashboard">
            <!-- Flash message -->
            <div v-if="$page.props.flash?.success" class="flash flash--success"><CheckCircle2 :size="16" /> {{ $page.props.flash.success }}</div>

            <!-- Progress Summary -->
            <section class="summary">
                <div class="summary__head">
                    <h2>Progress Summary</h2>
                    <p>Your current status for this program.</p>
                </div>
                <div class="summary__cards">
                    <div class="s-card">
                        <div class="s-card__icon-row">
                            <span class="ring-sm" :style="{ background: ringGradient }"><span class="ring-sm__hole"></span></span>
                            <span class="s-card__label">Learning Progress</span>
                        </div>
                        <div class="s-card__value">{{ program.hours_completed }} / {{ program.total_hours || '—' }} hrs</div>
                        <div class="bar bar--sm"><div class="bar__fill" :style="{ width: hoursPercent + '%' }"></div></div>
                        <div class="s-card__sub">{{ hoursPercent }}% complete</div>
                    </div>

                    <div class="s-card">
                        <div class="s-card__icon-row">
                            <span class="s-icon" :class="attendanceColorClass"><component :is="attendanceIcon" :size="18" /></span>
                            <span class="s-card__label">Attendance</span>
                        </div>
                        <div class="s-card__value">{{ attendanceCardText.label }}</div>
                        <div class="s-card__sub">{{ attendanceCardText.sub }}</div>
                    </div>

                    <button type="button" class="s-card s-card--clickable" @click="activeTab = 'requirements'">
                        <div class="s-card__icon-row">
                            <span class="s-icon" :class="requirementsActionsNeeded > 0 ? 'icon--gold' : 'icon--green'"
                                ><FileWarning :size="18"
                            /></span>
                            <span class="s-card__label">Requirements</span>
                        </div>
                        <div class="s-card__value">{{ requirementsSubmittedCount }} / {{ program.requirements_total }} submitted</div>
                        <div class="s-card__sub" :class="requirementsActionsNeeded > 0 ? 'text-amber' : 'text-green'">
                            {{
                                requirementsActionsNeeded > 0
                                    ? `${requirementsActionsNeeded} action${requirementsActionsNeeded > 1 ? 's' : ''} needed`
                                    : 'All caught up'
                            }}
                        </div>
                    </button>
                </div>
            </section>

            <!-- Sidebar -->
            <aside class="side">
                <div class="next-action" :class="nextActionRequirement ? 'next-action--pending' : 'next-action--done'">
                    <template v-if="nextActionRequirement">
                        <div class="next-action__eyebrow"><AlertCircle :size="14" /> Action Needed</div>
                        <h3>{{ nextActionRequirement.status === 'Rejected' ? 'Revise' : 'Submit' }}: {{ nextActionRequirement.name }}</h3>
                        <p v-if="nextActionRequirement.status === 'Rejected'">
                            {{ nextActionRequirement.remarks || 'Please review and resubmit this requirement.' }}
                        </p>
                        <p v-else>This requirement has not yet been submitted.</p>
                        <p class="next-action__due"><CalendarDays :size="13" /> Due: {{ formatDate(nextActionRequirement.due_date) }}</p>
                        <button type="button" class="next-action__btn" @click="switchToRequirement(nextActionRequirement)">
                            {{ nextActionRequirement.status === 'Rejected' ? 'Replace File' : 'Submit Requirement' }}
                            <ArrowRight :size="14" />
                        </button>
                    </template>
                    <template v-else>
                        <div class="next-action__eyebrow next-action__eyebrow--done"><CheckCircle2 :size="14" /> You're All Set</div>
                        <p>
                            {{
                                program.attendance === 'Absent'
                                    ? "You've submitted a non-attendance justification, so no further requirements are needed."
                                    : 'All required program requirements have been submitted.'
                            }}
                        </p>
                    </template>
                </div>

                <div class="journey-card">
                    <h3><Milestone :size="15" /> Program Journey</h3>
                    <ul class="journey-list">
                        <li v-for="step in programJourney" :key="step.key" class="journey-item" :class="`journey-item--${step.state}`">
                            <span class="journey-dot">
                                <CheckCircle2 v-if="step.state === 'done'" :size="16" />
                                <span v-else-if="step.state === 'current'" class="journey-dot__pulse"></span>
                                <CircleDashed v-else :size="16" />
                            </span>
                            <div class="journey-body">
                                <span class="journey-label">{{ step.label }}</span>
                                <span class="journey-date">{{ step.date ? formatDate(step.date) : step.state === 'done' ? '' : 'Pending' }}</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div v-if="program.attendance !== 'Absent'" class="attend-card">
                    <div class="attend-card__icon"><Info :size="16" /></div>
                    <div>
                        <h4>Unable to attend?</h4>
                        <p>If you were unable to attend the program, you may submit a justification memo.</p>
                        <button type="button" class="attend-card__btn" @click="activeTab = 'justification'">Submit Justification</button>
                    </div>
                </div>
                <div v-else class="attend-card attend-card--recorded">
                    <div class="attend-card__icon"><AlertCircle :size="16" /></div>
                    <div>
                        <h4>Non-Attendance Recorded</h4>
                        <p>{{ program.justification ? 'Your justification memo has been submitted.' : 'You are marked Absent for this program.' }}</p>
                        <button type="button" class="attend-card__btn" @click="activeTab = 'justification'">View Details</button>
                    </div>
                </div>

                <div v-if="lastUpdatedLabel" class="updated-card">
                    <Clock :size="13" />
                    <div>
                        <span class="updated-card__label">Last updated</span>
                        <span class="updated-card__value">{{ lastUpdatedLabel }}</span>
                    </div>
                </div>
            </aside>

            <!-- Tab Navigation -->
            <nav class="tabs-nav">
                <div class="tabs-nav__inner">
                    <div class="tabs-nav__scroll">
                        <button
                            v-for="tab in primaryTabs"
                            :key="tab.key"
                            type="button"
                            class="tab-btn"
                            :class="{ 'tab-btn--active': activeTab === tab.key }"
                            @click="
                                activeTab = tab.key;
                                moreOpen = false;
                            "
                        >
                            <component :is="tab.icon" :size="16" />
                            {{ tab.label }}
                            <span v-if="tab.badge" class="tab-btn__badge">{{ tab.badge }}</span>
                        </button>
                    </div>

                    <div v-if="secondaryTabs.length" class="tab-more">
                        <button type="button" class="tab-btn tab-btn--more" :class="{ 'tab-btn--active': isSecondaryActive }" @click="moreOpen = !moreOpen">
                            {{ moreButtonLabel }} <ChevronDown :size="14" :class="{ 'rotate-180': moreOpen }" />
                        </button>
                        <div v-if="moreOpen" class="tab-more__backdrop" @click="moreOpen = false"></div>
                        <div v-if="moreOpen" class="tab-more__menu">
                            <button
                                v-for="tab in secondaryTabs"
                                :key="tab.key"
                                type="button"
                                class="tab-more__item"
                                :class="{ 'tab-more__item--active': activeTab === tab.key }"
                                @click="
                                    activeTab = tab.key;
                                    moreOpen = false;
                                "
                            >
                                <component :is="tab.icon" :size="15" /> {{ tab.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="tab-content">
                <!-- About / Overview -->
                <section v-show="activeTab === 'about'" class="about">
                    <h2>Program Information</h2>
                    <div class="info-grid">
                        <div v-if="program.program_type" class="info-cell">
                            <span class="info-cell__label">Program Type</span>
                            <span class="info-cell__value">{{ program.program_type }}</span>
                        </div>
                        <div v-if="program.program_code" class="info-cell">
                            <span class="info-cell__label">Program Code</span>
                            <span class="info-cell__value">{{ program.program_code }}</span>
                        </div>
                        <div v-if="program.modality" class="info-cell">
                            <span class="info-cell__label">Modality</span>
                            <span class="info-cell__value">{{ program.modality }}</span>
                        </div>
                        <div v-if="program.venue" class="info-cell">
                            <span class="info-cell__label">Venue</span>
                            <span class="info-cell__value">{{ program.venue }}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-cell__label">Date</span>
                            <span class="info-cell__value">{{ formatDate(program.date_start) }} – {{ formatDate(program.date_end) }}</span>
                        </div>
                    </div>
                    <template v-if="program.program_description">
                        <h3 class="about__subhead">About this Program</h3>
                        <p class="about__desc">{{ program.program_description }}</p>
                    </template>
                </section>

                <!-- Requirements breakdown -->
                <section v-show="activeTab === 'requirements'" class="reqs">
                    <h2>Requirements &amp; Submissions</h2>
                    <p v-if="program.attendance === 'Absent'" class="reqs__sub reqs__sub--absent">
                        You were marked Absent for this program, so requirement submissions are no longer required.
                    </p>
                    <p v-else class="reqs__sub">Track the documents you need to submit and their current status.</p>

                    <div v-if="program.requirements_total > 0" class="reqs-progress">
                        <div class="reqs-progress__top">
                            <span class="reqs-progress__title">Requirements Progress</span>
                            <span class="reqs-progress__pct">{{ requirementsProgressPercent }}%</span>
                        </div>
                        <div class="bar bar--lg"><div class="bar__fill" :style="{ width: requirementsProgressPercent + '%' }"></div></div>
                        <div class="reqs-progress__bottom">
                            <span>{{ requirementsSubmittedCount }} of {{ program.requirements_total }} requirements submitted</span>
                            <div class="reqs__chips">
                                <span class="chip chip--approved"><CheckCircle2 :size="13" /> {{ program.requirements_approved }} Approved</span>
                                <span class="chip chip--pending"><Clock :size="13" /> {{ program.requirements_pending }} Pending Review</span>
                                <span class="chip chip--rejected"><XCircle :size="13" /> {{ program.requirements_rejected }} Revision Needed</span>
                                <span class="chip chip--missing"><FileWarning :size="13" /> {{ program.requirements_missing }} Not Submitted</span>
                            </div>
                        </div>
                    </div>

                    <div class="reqs__list">
                        <div
                            v-for="r in program.requirements"
                            :id="`req-${r.id}`"
                            :key="r.id"
                            class="req-card"
                            :class="{ 'req-card--missing': !r.status && r.is_required }"
                        >
                            <div class="req-card__top">
                                <component :is="reqIcon(r.status)" :size="22" :class="reqIconClass(r.status)" class="req-card__icon" />
                                <div class="req-card__main">
                                    <div class="req-card__title">{{ r.name }}</div>
                                    <div class="req-card__meta">
                                        Due {{ formatDate(r.due_date) }}
                                        <span v-if="!r.is_required" class="req-row__optional">&middot; Optional</span>
                                    </div>
                                </div>
                                <span class="req-badge" :class="reqBadgeClass(r.status)">{{ statusLabel(r.status) }}</span>
                            </div>

                            <div v-if="r.status === 'Rejected' && r.remarks" class="req-row__remarks">
                                <strong>Reviewer note:</strong> {{ r.remarks }}
                            </div>
                            <div v-if="r.notes && expandedRequirement !== r.id" class="req-row__notes-readonly">
                                <strong>Your note:</strong> {{ r.notes }}
                            </div>

                            <div class="req-card__bottom">
                                <div v-if="r.file_url" class="req-card__file">
                                    <FileText :size="14" />
                                    <span>{{ r.file_name }}</span>
                                    <span v-if="r.submitted_at" class="req-card__submitted">Submitted {{ formatDate(r.submitted_at) }}</span>
                                </div>
                                <div v-else class="req-card__file req-card__file--empty">No file submitted yet</div>

                                <div class="req-card__actions">
                                    <a v-if="r.file_url" :href="r.file_url" target="_blank" rel="noopener" class="req-btn req-btn--ghost">
                                        View Submission
                                    </a>
                                    <button
                                        v-if="r.status !== 'Approved' && program.attendance !== 'Absent'"
                                        type="button"
                                        class="req-btn req-btn--primary"
                                        @click="toggleRequirementExpand(r.id)"
                                    >
                                        {{ r.file_url ? 'Replace File' : 'Submit Requirement' }}
                                        <ChevronDown :size="14" :class="{ 'rotate-180': expandedRequirement === r.id }" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="expandedRequirement === r.id && r.status !== 'Approved' && program.attendance !== 'Absent'"
                                class="req-card__expand"
                            >
                                <div v-if="r.file_url" class="req-row__file">
                                    <a :href="r.file_url" target="_blank" rel="noopener" class="file-link"><FileText :size="14" /> {{ r.file_name }}</a>
                                    <button type="button" class="delete-btn" :disabled="deletingId === r.id" @click="confirmDelete(r)">
                                        <Trash2 :size="13" /> {{ deletingId === r.id ? 'Deleting…' : 'Delete' }}
                                    </button>
                                </div>
                                <form class="req-row__upload" @submit.prevent="submitFile(r)">
                                    <label class="upload-input">
                                        <UploadCloud :size="14" />
                                        <span>{{ selectedFile[r.id]?.name || (r.file_url ? 'Replace file (PDF)' : 'Choose PDF file') }}</span>
                                        <input type="file" accept="application/pdf" @change="onFileChange($event, r.id)" />
                                    </label>
                                    <button
                                        type="submit"
                                        class="upload-btn"
                                        :disabled="(!selectedFile[r.id] && noteDraft[r.id] === (r.notes || '')) || uploadingId === r.id"
                                    >
                                        {{ uploadingId === r.id ? 'Saving…' : r.file_url ? 'Save Changes' : 'Submit' }}
                                    </button>
                                </form>
                                <div class="req-row__notes">
                                    <textarea v-model="noteDraft[r.id]" placeholder="Add a note for the reviewer (optional)…" rows="2"></textarea>
                                </div>
                                <div v-if="fileError[r.id]" class="req-row__error">{{ fileError[r.id] }}</div>
                            </div>
                        </div>
                        <div v-if="!program.requirements.length" class="reqs__empty">
                            <CheckCircle2 :size="18" /> No requirements have been set for this batch yet.
                        </div>
                    </div>
                </section>

                <!-- Absence Justification -->
                <section v-show="activeTab === 'justification'" class="justification">
                    <div v-if="program.attendance === 'Absent' && program.justification" class="justification__banner">
                        <AlertCircle :size="20" />
                        <div>
                            <p class="justification__banner-title">You were marked as Absent for this program</p>
                            <p class="justification__banner-sub">
                                Your justification memo has been recorded. You no longer need to submit the requirements above.
                            </p>
                        </div>
                    </div>

                    <h2>Non-Attendance Justification</h2>
                    <p class="reqs__sub">
                        Unable to attend or complete this program? Upload your justification memo (PDF) — you don't need to have submitted any
                        requirements first. This will automatically mark you as Absent and you won't be asked to submit the requirements anymore.
                    </p>

                    <div v-if="program.justification" class="req-row__file">
                        <a :href="program.justification.file_url" target="_blank" rel="noopener" class="file-link">
                            <FileText :size="14" /> View uploaded justification
                        </a>
                        <button type="button" class="delete-btn" :disabled="deletingJustification" @click="confirmDeleteJustification">
                            <Trash2 :size="13" /> {{ deletingJustification ? 'Removing…' : 'Remove' }}
                        </button>
                    </div>

                    <form class="req-row__upload" @submit.prevent="submitJustification">
                        <label class="upload-input">
                            <UploadCloud :size="14" />
                            <span>{{ justificationFile?.name || (program.justification ? 'Replace file (PDF)' : 'Choose PDF file') }}</span>
                            <input type="file" accept="application/pdf" @change="onJustificationFileChange" />
                        </label>
                        <button type="submit" class="upload-btn" :disabled="!justificationFile || uploadingJustification">
                            {{ uploadingJustification ? 'Uploading…' : 'Upload Justification' }}
                        </button>
                    </form>
                    <div v-if="justificationError" class="req-row__error">{{ justificationError }}</div>
                </section>

                <!-- CERTIFICATES SECTION -->
                <section v-show="activeTab === 'certificates'" class="certs">
                    <div class="certs__header">
                        <div class="certs__title-group">
                            <div class="certs__icon-wrap">
                                <Award :size="24" color="#fff" />
                            </div>
                            <div>
                                <h2>My Certificates</h2>
                                <p class="certs__sub">Upload your training certificates for this program. PDF only • Maximum 10MB.</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="program.certificates && program.certificates.length" class="cert-grid">
                        <div v-for="cert in program.certificates" :key="cert.id" class="cert-card">
                            <div class="cert-card__ribbon" :class="`cert-card__ribbon--${cert.status.toLowerCase()}`">
                                {{ cert.status }}
                            </div>
                            <div class="cert-card__top">
                                <span class="cert-card__emoji" :style="{ background: `${certTypeColor(cert.type)}18` }">
                                    <component :is="certTypeIconComponent(cert.type)" :size="22" :color="certTypeColor(cert.type)" />
                                </span>
                                <div>
                                    <p class="cert-card__type">{{ certTypeLabel(cert.type) }}</p>
                                    <p class="cert-card__number">{{ cert.certificate_number || '—' }}</p>
                                </div>
                            </div>
                            <div class="cert-card__meta">
                                <span v-if="cert.issued_date">📅 Issued: {{ formatDate(cert.issued_date) }}</span>
                                <span v-if="cert.hours > 0">⏱ {{ cert.hours }} hr(s)</span>
                                <span v-if="cert.issued_by" class="cert-card__uploader">✍️ Signed by: {{ cert.issued_by }}</span>
                            </div>
                            <p v-if="cert.remarks" class="cert-card__remarks">{{ cert.remarks }}</p>
                            <div class="cert-card__actions">
                                <a v-if="cert.file_url" :href="cert.file_url" target="_blank" rel="noopener" class="cert-btn cert-btn--view">
                                    <FileText :size="13" /> View PDF
                                </a>
                                <span v-else class="cert-card__no-file">No file yet</span>
                                <button
                                    v-if="cert.status !== 'Issued'"
                                    type="button"
                                    class="cert-btn cert-btn--delete"
                                    :disabled="deletingCertId === cert.id"
                                    @click="confirmDeleteCert(cert)"
                                >
                                    <Trash2 :size="13" /> {{ deletingCertId === cert.id ? 'Removing…' : 'Remove' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="certs__empty">
                        <div class="certs__empty-icon"><Award :size="48" color="#c7d2fe" /></div>
                        <p class="certs__empty-title">No certificates yet</p>
                        <p class="certs__empty-sub">Your certificate will appear here once issued or uploaded.</p>
                    </div>

                    <div class="cert-upload">
                        <div class="cert-upload__header">
                            <span class="cert-upload__title">Upload a Certificate</span>
                            <span class="cert-upload__hint">PDF • Maximum 10MB</span>
                        </div>
                        <div class="cert-upload__row">
                            <div class="cert-upload__field">
                                <label class="cert-upload__label">Certificate Type</label>
                                <select v-model="certType" class="cert-upload__select">
                                    <option v-for="t in CERT_TYPES" :key="t.value" :value="t.value" :disabled="existingCertTypes.has(t.value)">
                                        {{ t.label }}{{ existingCertTypes.has(t.value) ? ' (already uploaded)' : '' }}
                                    </option>
                                </select>
                                <p v-if="selectedTypeAlreadyExists" class="cert-type-warning">
                                    ⚠ You already have a {{ certTypeLabel(certType) }}. Remove it first before uploading a new one.
                                </p>
                            </div>
                            <div class="cert-upload__field cert-upload__field--file">
                                <label class="cert-upload__label">PDF File</label>
                                <label class="cert-file-btn">
                                    <UploadCloud :size="14" />
                                    <span>{{ certFile?.name || 'Choose PDF file…' }}</span>
                                    <input type="file" accept="application/pdf" @change="onCertFileChange" />
                                </label>
                            </div>
                            <button
                                type="button"
                                class="cert-upload__btn"
                                :disabled="!certFile || uploadingCert || selectedTypeAlreadyExists"
                                @click="uploadCertificate"
                            >
                                <UploadCloud v-if="!uploadingCert" :size="14" />
                                {{ uploadingCert ? 'Uploading…' : 'Upload Certificate' }}
                            </button>
                        </div>
                        <p v-if="certError" class="cert-upload__error">⚠ {{ certError }}</p>
                    </div>
                </section>

                <!-- Resource Speakers -->
                <section v-show="activeTab === 'speakers'" class="speakers">
                    <h2>Resource Speakers</h2>
                    <p class="reqs__sub">Facilitators and resource persons engaged for this program.</p>
                    <div v-if="program.resource_speakers?.length" class="speakers__grid">
                        <div v-for="s in program.resource_speakers" :key="s.id" class="speaker-card">
                            <div class="speaker-card__avatar"><Mic2 :size="18" /></div>
                            <div class="speaker-card__name">{{ s.name }}</div>
                            <div v-if="s.designation || s.affiliation" class="speaker-card__role">
                                {{ [s.designation, s.affiliation].filter(Boolean).join(' — ') }}
                            </div>
                            <div v-if="s.topic" class="speaker-card__topic"><BookOpen :size="13" /> {{ s.topic }}</div>
                            <div v-if="s.date_engaged" class="speaker-card__date"><CalendarDays :size="13" /> {{ formatDate(s.date_engaged) }}</div>
                        </div>
                    </div>
                    <div v-else class="empty-note">No speakers listed for this program.</div>
                </section>

                <!-- Supporting Documents -->
                <section v-show="activeTab === 'documents'" class="docs">
                    <h2>Supporting Documents</h2>
                    <p class="reqs__sub">Official memos, orders, and circulars related to this program.</p>
                    <div v-if="program.supporting_documents?.length" class="docs__grid">
                        <div v-for="d in program.supporting_documents" :key="d.id" class="doc-card">
                            <div class="doc-card__top">
                                <span class="doc-card__type">{{ d.document_type }}</span>
                                <span v-if="d.document_series" class="doc-card__series">S.Y. {{ d.document_series }}</span>
                            </div>
                            <h3 class="doc-card__subject">{{ d.subject }}</h3>
                            <div class="doc-card__meta">
                                <span v-if="d.document_number"><Hash :size="13" /> {{ d.document_number }}</span>
                                <span v-if="d.origin"><Building2 :size="13" /> {{ d.origin }}</span>
                                <span v-if="d.date_issued"><CalendarDays :size="13" /> {{ formatDate(d.date_issued) }}</span>
                            </div>
                            <a v-if="d.link" :href="d.link" target="_blank" rel="noopener" class="doc-card__link">
                                <ExternalLink :size="13" /> View document
                            </a>
                        </div>
                    </div>
                    <div v-else class="empty-note">No supporting documents available.</div>
                </section>
            </div>
        </div>

        <TheFooter />
    </div>
</template>

<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    Award,
    BadgeCheck,
    BookOpen,
    Building2,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    CircleDashed,
    ClipboardCheck,
    Clock,
    ExternalLink,
    FileText,
    FileWarning,
    Hash,
    Info,
    Layers,
    MapPin,
    Medal,
    Mic2,
    Milestone,
    Sparkles,
    Star,
    Tag,
    Trash2,
    Trophy,
    UploadCloud,
    XCircle,
} from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import TheFooter from '../Welcome/sections/TheFooter.vue';
import TheNavbar from '../Welcome/sections/TheNavbar.vue';

const { confirmDialog } = useConfirm();

const props = defineProps({
    program: { type: Object, required: true },
});

/* ---- Tabs: split the page into panels instead of one long scroll ---- */
const TABS = computed(() => {
    const tabs = [];
    if (props.program.program_description || props.program.program_type) {
        tabs.push({ key: 'about', label: 'Overview', icon: Info });
    }
    tabs.push({
        key: 'requirements',
        label: 'Requirements',
        icon: ClipboardCheck,
        badge: props.program.requirements_missing > 0 ? props.program.requirements_missing : null,
    });
    tabs.push({ key: 'justification', label: 'Non-Attendance', icon: AlertCircle });
    tabs.push({ key: 'certificates', label: 'Certificates', icon: Award });
    if (props.program.resource_speakers?.length) {
        tabs.push({ key: 'speakers', label: 'Speakers', icon: Mic2 });
    }
    if (props.program.supporting_documents?.length) {
        tabs.push({ key: 'documents', label: 'Documents', icon: FileText });
    }
    return tabs;
});

const activeTab = ref('requirements');

/* ---- Primary vs. secondary tab grouping for the redesigned tab bar ---- */
const PRIMARY_TAB_ORDER = ['about', 'requirements', 'certificates'];
const primaryTabs = computed(() => PRIMARY_TAB_ORDER.map((key) => TABS.value.find((t) => t.key === key)).filter(Boolean));
const secondaryTabs = computed(() => TABS.value.filter((t) => !PRIMARY_TAB_ORDER.includes(t.key)));
const moreOpen = ref(false);
const isSecondaryActive = computed(() => secondaryTabs.value.some((t) => t.key === activeTab.value));
const moreButtonLabel = computed(() => secondaryTabs.value.find((t) => t.key === activeTab.value)?.label ?? 'More');

/* ---- Note drafts ---- */
const noteDraft = ref(Object.fromEntries(props.program.requirements.map((r) => [r.id, r.notes || ''])));

/* ---- Stats computed ---- */
const hoursPercent = computed(() => {
    if (props.program.total_hours > 0) {
        return Math.min(100, Math.round((props.program.hours_completed / props.program.total_hours) * 100));
    }
    return props.program.attendance === 'Complete' ? 100 : 0;
});
const ringGradient = computed(() => `conic-gradient(#1d3fc4 ${hoursPercent.value}%, #e5e7eb ${hoursPercent.value}%)`);
const attendanceIcon = computed(() => {
    if (props.program.attendance === 'Complete') return CheckCircle2;
    if (props.program.attendance === 'Absent') return AlertCircle;
    return Clock;
});
const attendanceColorClass = computed(() => {
    if (props.program.attendance === 'Complete') return 'icon--green';
    if (props.program.attendance === 'Absent') return 'icon--red';
    return 'icon--gold';
});

/* ---- Hero status presentation ---- */
const heroStatus = computed(() => {
    const p = props.program;
    if (p.attendance === 'Absent') {
        return { label: 'Non-Attendance Recorded', icon: AlertCircle, tone: 'muted' };
    }
    if (p.total_hours > 0 && hoursPercent.value >= 100) {
        return { label: 'Program Completed', icon: CheckCircle2, tone: 'green' };
    }
    if (p.hours_completed <= 0 && p.attendance === 'Pending') {
        return { label: 'Attendance Pending', icon: Clock, tone: 'amber' };
    }
    return { label: 'In Progress', icon: CheckCircle2, tone: 'green' };
});

const attendanceCardText = computed(() => {
    if (props.program.attendance === 'Complete') return { label: 'Complete', sub: 'All sessions attended' };
    if (props.program.attendance === 'Absent') return { label: 'Absent', sub: 'Attendance requires attention' };
    return { label: 'Pending', sub: 'Attendance is still being recorded' };
});

/* ---- Requirements summary computed ---- */
const requirementsSubmittedCount = computed(() => props.program.requirements_total - props.program.requirements_missing);
const requirementsActionsNeeded = computed(() => props.program.requirements_missing + props.program.requirements_rejected);
const requirementsProgressPercent = computed(() =>
    props.program.requirements_total > 0 ? Math.round((requirementsSubmittedCount.value / props.program.requirements_total) * 100) : 0,
);

/* ---- Next action: the single most urgent requirement to fix ---- */
const nextActionRequirement = computed(() => {
    if (props.program.attendance === 'Absent') return null;
    const candidates = props.program.requirements.filter((r) => r.is_required && (r.status === 'Rejected' || !r.status));
    return (
        [...candidates].sort((a, b) => {
            if (a.status === 'Rejected' && b.status !== 'Rejected') return -1;
            if (b.status === 'Rejected' && a.status !== 'Rejected') return 1;
            return new Date(a.due_date || 0).getTime() - new Date(b.due_date || 0).getTime();
        })[0] ?? null
    );
});

/* ---- Compact visual timeline built only from data we actually have ---- */
const programJourney = computed(() => {
    const p = props.program;
    const now = new Date();
    const started = p.date_start ? now >= new Date(p.date_start) : false;
    const attendanceDone = p.attendance !== 'Pending' || p.hours_completed > 0;
    const reqTotal = p.requirements_total ?? 0;
    const reqAllSubmitted = reqTotal === 0 || p.requirements_missing === 0;
    const reqAllApproved = reqTotal === 0 || p.requirements_approved === reqTotal;
    const issuedCert = (p.certificates ?? []).find((c) => c.status === 'Issued');
    const submittedDates = (p.requirements ?? [])
        .map((r) => r.submitted_at)
        .filter(Boolean)
        .sort();
    const lastSubmittedDate = submittedDates[submittedDates.length - 1] ?? null;

    const steps = [
        { key: 'enrolled', label: 'Enrolled', done: true, date: null },
        { key: 'started', label: 'Training Started', done: started, date: started ? p.date_start : null },
        { key: 'attendance', label: 'Attendance Recorded', done: attendanceDone, date: null },
        { key: 'submitted', label: 'Requirements Submitted', done: reqAllSubmitted, date: reqAllSubmitted ? lastSubmittedDate : null },
        { key: 'approved', label: 'Requirements Approved', done: reqAllApproved, date: null },
        { key: 'certificate', label: 'Certificate Issued', done: !!issuedCert, date: issuedCert?.issued_date ?? null },
    ];

    let currentAssigned = false;
    return steps.map((s) => {
        let state = 'upcoming';
        if (s.done) {
            state = 'done';
        } else if (!currentAssigned) {
            state = 'current';
            currentAssigned = true;
        }
        return { ...s, state };
    });
});

/* ---- Last updated: derived only from real timestamps, never invented ---- */
const lastUpdatedAt = computed(() => {
    const dates = [...props.program.requirements.map((r) => r.submitted_at), props.program.justification?.uploaded_at]
        .filter(Boolean)
        .map((d) => new Date(d));
    if (!dates.length) return null;
    return new Date(Math.max(...dates.map((d) => d.getTime())));
});
const lastUpdatedLabel = computed(() => {
    if (!lastUpdatedAt.value) return null;
    const datePart = lastUpdatedAt.value.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    const timePart = lastUpdatedAt.value.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    return `${datePart} · ${timePart}`;
});

/* ---- Requirement helpers ---- */
function reqIcon(status) {
    if (status === 'Approved') return CheckCircle2;
    if (status === 'Rejected') return XCircle;
    if (status === 'Pending') return Clock;
    return CircleDashed;
}
function reqIconClass(status) {
    if (status === 'Approved') return 'icon--green';
    if (status === 'Rejected') return 'icon--red';
    if (status === 'Pending') return 'icon--gold';
    return 'icon--muted';
}
function reqBadgeClass(status) {
    if (status === 'Approved') return 'req-row__status--approved';
    if (status === 'Rejected') return 'req-row__status--rejected';
    if (status === 'Pending') return 'req-row__status--pending';
    return 'req-row__status--missing';
}
function statusLabel(status) {
    if (status === 'Approved') return 'Approved';
    if (status === 'Pending') return 'Pending Review';
    if (status === 'Rejected') return 'Revision Needed';
    return 'Not Submitted';
}
function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

/* ---- Inline expand/collapse for a requirement's upload controls ---- */
const expandedRequirement = ref(null);
function toggleRequirementExpand(id) {
    expandedRequirement.value = expandedRequirement.value === id ? null : id;
}
function switchToRequirement(requirement) {
    activeTab.value = 'requirements';
    expandedRequirement.value = requirement.id;
    nextTick(() => {
        document.getElementById(`req-${requirement.id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

/* ---- Requirement file submission ---- */
const selectedFile = ref({});
const fileError = ref({});
const uploadingId = ref(null);
const deletingId = ref(null);

function onFileChange(event, requirementId) {
    const file = event.target.files[0];
    fileError.value[requirementId] = null;
    if (file && file.type !== 'application/pdf') {
        fileError.value[requirementId] = 'Only PDF files are accepted.';
        selectedFile.value[requirementId] = null;
        return;
    }
    if (file && file.size > 20 * 1024 * 1024) {
        fileError.value[requirementId] = 'File must not exceed 20MB.';
        selectedFile.value[requirementId] = null;
        return;
    }
    selectedFile.value[requirementId] = file;
}

function submitFile(requirement) {
    const file = selectedFile.value[requirement.id];
    uploadingId.value = requirement.id;
    const formData = new FormData();
    if (file) formData.append('file', file);
    formData.append('notes', noteDraft.value[requirement.id] || '');
    router.post(route('programs.my-progress.submit', { batch: props.program.batch_id, requirement: requirement.id }), formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            selectedFile.value[requirement.id] = null;
        },
        onError: (errors) => {
            fileError.value[requirement.id] = errors.file || 'Save failed. Please try again.';
        },
        onFinish: () => {
            uploadingId.value = null;
        },
    });
}

async function confirmDelete(requirement) {
    if (!(await confirmDialog(`Delete your submission for "${requirement.name}"? You'll need to submit again.`))) return;
    deletingId.value = requirement.id;
    router.delete(route('programs.my-progress.destroy', { batch: props.program.batch_id, requirement: requirement.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

/* ---- Absence justification ---- */
const justificationFile = ref(null);
const justificationError = ref('');
const uploadingJustification = ref(false);
const deletingJustification = ref(false);

function onJustificationFileChange(event) {
    justificationError.value = '';
    const file = event.target.files[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
        justificationError.value = 'Only PDF files are accepted.';
        justificationFile.value = null;
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        justificationError.value = 'File must not exceed 10MB.';
        justificationFile.value = null;
        return;
    }
    justificationFile.value = file;
}

function submitJustification() {
    if (!justificationFile.value) {
        justificationError.value = 'Please choose a PDF file.';
        return;
    }
    uploadingJustification.value = true;
    justificationError.value = '';
    const formData = new FormData();
    formData.append('file', justificationFile.value);
    router.post(route('programs.my-progress.justification.submit', { batch: props.program.batch_id }), formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            justificationFile.value = null;
        },
        onError: (errors) => {
            justificationError.value = errors.file || 'Upload failed. Please try again.';
        },
        onFinish: () => {
            uploadingJustification.value = false;
        },
    });
}

async function confirmDeleteJustification() {
    if (!(await confirmDialog('Remove your justification memo? Your attendance status will revert to Pending.'))) return;
    deletingJustification.value = true;
    router.delete(route('programs.my-progress.justification.destroy', { batch: props.program.batch_id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingJustification.value = false;
        },
    });
}

/* ════════════════════════════════════════
   CERTIFICATE SECTION
════════════════════════════════════════ */

const CERT_TYPES = [
    { value: 'Participation', label: 'Certificate of Participation', icon: Medal, color: '#1d3fc4' },
    { value: 'Completion', label: 'Certificate of Completion', icon: Award, color: '#059669' },
    { value: 'Appearance', label: 'Certificate of Appearance', icon: Mic2, color: '#7c3aed' },
    { value: 'Appreciation', label: 'Certificate of Appreciation', icon: Star, color: '#d97706' },
    { value: 'Recognition', label: 'Certificate of Recognition', icon: Trophy, color: '#dc2626' },
    { value: 'Achievement', label: 'Certificate of Achievement', icon: Sparkles, color: '#0891b2' },
];

// Returns the lucide component for a cert type
function certTypeIconComponent(type) {
    return CERT_TYPES.find((t) => t.value === type)?.icon ?? BadgeCheck;
}
function certTypeColor(type) {
    return CERT_TYPES.find((t) => t.value === type)?.color ?? '#1d3fc4';
}

const certFile = ref(null);
const certType = ref('Participation');
const certError = ref('');
const uploadingCert = ref(false);
const deletingCertId = ref(null);

// Set ng mga types na may existing na certificate — hindi na pwedeng mag-upload ulit
const existingCertTypes = computed(() => new Set((props.program.certificates ?? []).map((c) => c.type)));
const selectedTypeAlreadyExists = computed(() => existingCertTypes.value.has(certType.value));

function certTypeLabel(type) {
    return CERT_TYPES.find((t) => t.value === type)?.label ?? type;
}

function onCertFileChange(e) {
    certError.value = '';
    const file = e.target.files[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
        certError.value = 'Only PDF files are accepted.';
        certFile.value = null;
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        certError.value = 'File must not exceed 10MB.';
        certFile.value = null;
        return;
    }
    certFile.value = file;
}

function uploadCertificate() {
    if (!certFile.value) {
        certError.value = 'Please choose a PDF file.';
        return;
    }
    if (existingCertTypes.value.has(certType.value)) {
        certError.value = 'You already have a certificate of this type. Remove it first.';
        return;
    }
    uploadingCert.value = true;
    certError.value = '';
    const formData = new FormData();
    formData.append('file', certFile.value);
    formData.append('type', certType.value);
    router.post(route('certificates.upload-by-user', { batch: props.program.batch_id }), formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            certFile.value = null;
        },
        onError: (e) => {
            certError.value = e.file || 'Upload failed. Please try again.';
        },
        onFinish: () => {
            uploadingCert.value = false;
        },
    });
}

async function confirmDeleteCert(cert) {
    if (!(await confirmDialog(`Remove your ${cert.type} certificate? This cannot be undone.`))) return;
    deletingCertId.value = cert.id;
    router.delete(route('certificates.destroy-by-user', { batch: props.program.batch_id, certificate: cert.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingCertId.value = null;
        },
    });
}
</script>

<style scoped>
.progress-page {
    --tdi-blue: #1d3fc4;
    --tdi-navy: #1a2744;
    --tdi-green: #0ca678;
    --tdi-green-bg: #ecfdf5;
    --tdi-green-text: #065f46;
    --tdi-amber: #f59f00;
    --tdi-amber-bg: #fffbeb;
    --tdi-amber-text: #92400e;
    --tdi-red: #e03131;
    --tdi-red-bg: #fef2f2;
    --tdi-red-text: #991b1b;
    --tdi-slate: #6b7280;
    --tdi-slate-bg: #f3f4f6;
    --tdi-border: #e5e7eb;
    font-family: 'Inter', system-ui, sans-serif;
    color: #1a2744;
    color-scheme: light;
    background: #f7f9fd;
    overflow-wrap: break-word;
}

.tone-green {
    color: var(--tdi-green-text);
}
.tone-amber {
    color: var(--tdi-amber-text);
}
.tone-red {
    color: var(--tdi-red-text);
}
.tone-muted {
    color: var(--tdi-slate);
}
.text-green {
    color: var(--tdi-green-text);
}
.text-amber {
    color: var(--tdi-amber-text);
}

/* Hero */
.ph {
    position: relative;
    /* Horizontal gutter comes from .ph__inner's own width/margin-inline below,
       the same mechanism .dashboard uses — keeps hero content aligned with the
       dashboard content beneath it at every viewport width. */
    padding: 8.5rem 0 3rem;
    overflow: hidden;
    background: #0f1c48;
}
.ph__bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
}
.ph__overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(10, 21, 60, 0.92) 0%, rgba(10, 21, 60, 0.72) 100%);
}
.ph__inner {
    position: relative;
    width: min(100% - 2rem, 1120px);
    margin-inline: auto;
}
.ph__back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.82rem;
    margin-bottom: 1.5rem;
}
.ph__back:hover {
    color: #fff;
}
.ph__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 2rem;
}
.ph__title-col {
    flex: 1;
    min-width: 0;
}
.ph__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.9rem;
}
.ph__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    padding: 0.3rem 0.65rem;
    border-radius: 20px;
    background: rgba(245, 184, 0, 0.16);
    color: #f5b800;
}
.ph__badge--muted {
    background: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.8);
}
.ph h1 {
    font-size: clamp(1.6rem, 3.4vw, 2.35rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 0.9rem;
}
.ph__meta {
    display: flex;
    gap: 1.25rem;
    flex-wrap: wrap;
}
.ph__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.83rem;
    color: rgba(255, 255, 255, 0.78);
}
.ph__progress-card {
    width: 290px;
    flex-shrink: 0;
    background: #fff;
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 8px 24px rgba(8, 15, 45, 0.25);
}
.ph__status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    margin-bottom: 0.6rem;
}
.ph__progress-line {
    font-size: 0.83rem;
    color: #4b5563;
    margin-bottom: 0.7rem;
}
.ph__progress-bar {
    height: 8px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
    margin-bottom: 0.4rem;
}
.ph__progress-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--tdi-green);
    transition: width 0.3s ease;
}
.ph__progress-pct {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--tdi-navy);
}

/* Dashboard grid */
.dashboard {
    width: min(100% - 2rem, 1120px);
    margin-inline: auto;
    padding-block: 1.75rem 4rem;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 336px;
    grid-template-areas:
        'flash flash'
        'summary side'
        'tabs side'
        'content side';
    gap: 1.5rem 1.75rem;
    align-items: start;
}
@media (max-width: 900px) {
    .dashboard {
        grid-template-columns: 1fr;
        grid-template-areas:
            'flash'
            'summary'
            'side'
            'tabs'
            'content';
    }
    .ph {
        padding-top: 6.5rem;
    }
    .ph__top {
        flex-direction: column;
    }
    .ph__progress-card {
        width: 100%;
    }
}

/* Flash */
.flash {
    grid-area: flash;
    padding: 0.85rem 1.25rem;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
}
.flash--success {
    background: var(--tdi-green-bg);
    color: var(--tdi-green-text);
}

/* Progress Summary */
.summary {
    grid-area: summary;
}
.summary__head {
    margin-bottom: 1rem;
}
.summary__head h2 {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--tdi-navy);
}
.summary__head p {
    font-size: 0.85rem;
    color: var(--tdi-slate);
    margin-top: 0.15rem;
}
.summary__cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
.s-card {
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 2px 10px rgba(15, 28, 72, 0.05);
    text-align: left;
    font-family: inherit;
    cursor: default;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.s-card--clickable {
    cursor: pointer;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
}
.s-card--clickable:hover {
    border-color: var(--tdi-blue);
    box-shadow: 0 4px 16px rgba(29, 63, 196, 0.12);
}
.s-card__icon-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.s-card__label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--tdi-slate);
}
.s-card__value {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
}
.s-card__sub {
    font-size: 0.78rem;
    color: var(--tdi-slate);
}
.s-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.ring-sm {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    padding: 3px;
    flex-shrink: 0;
}
.ring-sm__hole {
    display: block;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #fff;
}
.bar {
    height: 6px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
}
.bar--lg {
    height: 9px;
    margin-bottom: 0.6rem;
}
.bar__fill {
    height: 100%;
    border-radius: 999px;
    background: var(--tdi-blue);
    transition: width 0.3s ease;
}
.icon--green {
    color: var(--tdi-green);
}
.icon--gold {
    color: var(--tdi-amber);
}
.icon--red {
    color: var(--tdi-red);
}
.icon--muted {
    color: #9ca3af;
}

/* Sidebar */
.side {
    grid-area: side;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.next-action {
    background: #fff;
    border: 1.5px solid var(--tdi-amber);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 2px 10px rgba(15, 28, 72, 0.05);
}
.next-action--done {
    border-color: var(--tdi-green);
}
.next-action__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--tdi-amber-text);
    margin-bottom: 0.5rem;
}
.next-action__eyebrow--done {
    color: var(--tdi-green-text);
}
.next-action h3 {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.35rem;
}
.next-action p {
    font-size: 0.8rem;
    color: #4b5563;
    line-height: 1.5;
    margin-bottom: 0.6rem;
}
.next-action__due {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    color: var(--tdi-slate);
    margin-bottom: 0.8rem !important;
}
.next-action__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    background: var(--tdi-blue);
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.55rem 1rem;
    border-radius: 9px;
    cursor: pointer;
    width: 100%;
    justify-content: center;
    transition: background 0.15s;
}
.next-action__btn:hover {
    background: #1535a8;
}

.journey-card,
.attend-card,
.updated-card {
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 2px 10px rgba(15, 28, 72, 0.05);
}
.journey-card h3 {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.9rem;
}
.journey-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.journey-item {
    position: relative;
    display: flex;
    gap: 0.7rem;
    padding-bottom: 1.1rem;
}
.journey-item:last-child {
    padding-bottom: 0;
}
.journey-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 7.5px;
    top: 20px;
    bottom: 0;
    width: 1.5px;
    background: var(--tdi-border);
}
.journey-item--done:not(:last-child)::before {
    background: var(--tdi-green);
}
.journey-dot {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #cbd5e1;
    z-index: 1;
    background: #fff;
}
.journey-item--done .journey-dot {
    color: var(--tdi-green);
}
.journey-item--current .journey-dot {
    color: var(--tdi-blue);
}
.journey-dot__pulse {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--tdi-blue);
}
.journey-body {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.journey-label {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--tdi-navy);
}
.journey-item--upcoming .journey-label {
    color: #9ca3af;
}
.journey-date {
    font-size: 0.72rem;
    color: var(--tdi-slate);
}

.attend-card {
    display: flex;
    gap: 0.75rem;
}
.attend-card__icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: #eef1fc;
    color: var(--tdi-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.attend-card--recorded .attend-card__icon {
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
}
.attend-card h4 {
    font-size: 0.88rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.3rem;
}
.attend-card p {
    font-size: 0.78rem;
    color: var(--tdi-slate);
    line-height: 1.45;
    margin-bottom: 0.6rem;
}
.attend-card__btn {
    border: 1.5px solid var(--tdi-blue);
    background: #fff;
    color: var(--tdi-blue);
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.4rem 0.85rem;
    border-radius: 8px;
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;
}
.attend-card__btn:hover {
    background: var(--tdi-blue);
    color: #fff;
}

.updated-card {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: var(--tdi-slate);
}
.updated-card__label {
    display: block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.updated-card__value {
    display: block;
    font-size: 0.8rem;
    color: var(--tdi-navy);
    font-weight: 600;
    margin-top: 0.1rem;
}

/* Tabs */
.tabs-nav {
    grid-area: tabs;
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 12px;
    padding: 0.35rem;
}
.tabs-nav__inner {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.tabs-nav__scroll {
    display: flex;
    gap: 0.25rem;
    overflow-x: auto;
    min-width: 0;
}
.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    white-space: nowrap;
    padding: 0.65rem 0.9rem;
    border: none;
    background: none;
    border-radius: 8px;
    font-family: inherit;
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--tdi-slate);
    cursor: pointer;
    transition:
        color 0.15s,
        background 0.15s;
}
.tab-btn:hover {
    color: var(--tdi-blue);
    background: #f7f9fd;
}
.tab-btn--active {
    color: #fff;
    background: var(--tdi-blue);
}
.tab-btn__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: var(--tdi-amber);
    color: #fff;
    font-size: 0.68rem;
    font-weight: 800;
}
.tab-btn--active .tab-btn__badge {
    background: rgba(255, 255, 255, 0.9);
    color: var(--tdi-blue);
}
.tab-more {
    position: relative;
    flex-shrink: 0;
}
.rotate-180 {
    transform: rotate(180deg);
    transition: transform 0.15s;
}
.tab-more__backdrop {
    position: fixed;
    inset: 0;
    z-index: 29;
}
.tab-more__menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 30;
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(15, 28, 72, 0.14);
    padding: 0.35rem;
    min-width: 190px;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.tab-more__item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.7rem;
    border: none;
    background: none;
    border-radius: 7px;
    font-family: inherit;
    font-size: 0.83rem;
    font-weight: 600;
    color: #374151;
    text-align: left;
    cursor: pointer;
}
.tab-more__item:hover {
    background: #f7f9fd;
}
.tab-more__item--active {
    color: var(--tdi-blue);
    background: #eef1fc;
}

.tab-content {
    grid-area: content;
    min-width: 0;
}
.tab-content > section {
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 14px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 2px 10px rgba(15, 28, 72, 0.05);
}

/* About / Overview */
.about h2 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 1rem;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--tdi-border);
}
.info-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.info-cell__label {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #9ca3af;
}
.info-cell__value {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--tdi-navy);
}
.about__subhead {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.5rem;
}
.about__desc {
    font-size: 0.88rem;
    line-height: 1.65;
    color: #4b5563;
}

/* Requirements */
.reqs h2 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.4rem;
}
.reqs__sub {
    color: var(--tdi-slate);
    font-size: 0.86rem;
    margin-bottom: 1.25rem;
}
.reqs__sub--absent {
    color: var(--tdi-red-text);
    font-weight: 600;
}
.reqs-progress {
    background: #f7f9fd;
    border: 1px solid var(--tdi-border);
    border-radius: 12px;
    padding: 1rem 1.15rem;
    margin-bottom: 1.5rem;
}
.reqs-progress__top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--tdi-navy);
    margin-bottom: 0.5rem;
}
.reqs-progress__pct {
    color: var(--tdi-blue);
    font-weight: 800;
}
.reqs-progress__bottom {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    font-size: 0.78rem;
    color: var(--tdi-slate);
}
.reqs__chips {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.3rem 0.65rem;
    border-radius: 20px;
}
.chip--approved {
    background: var(--tdi-green-bg);
    color: var(--tdi-green-text);
}
.chip--pending {
    background: var(--tdi-amber-bg);
    color: var(--tdi-amber-text);
}
.chip--rejected {
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
}
.chip--missing {
    background: var(--tdi-slate-bg);
    color: #4b5563;
}
.reqs__list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.req-card {
    background: #fff;
    border: 1px solid var(--tdi-border);
    border-radius: 12px;
    padding: 1rem 1.15rem;
    border-left: 3px solid transparent;
}
.req-card--missing {
    border-left-color: var(--tdi-amber);
}
.req-card__top {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.req-card__icon {
    flex-shrink: 0;
    margin-top: 0.1rem;
}
.req-card__main {
    flex: 1;
    min-width: 0;
}
.req-card__title {
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--tdi-navy);
}
.req-card__meta {
    font-size: 0.76rem;
    color: #9ca3af;
    margin-top: 0.15rem;
}
.req-row__optional {
    font-style: italic;
}
.req-badge {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.3rem 0.7rem;
    border-radius: 20px;
    white-space: nowrap;
    flex-shrink: 0;
}
.req-row__status--approved {
    background: var(--tdi-green-bg);
    color: var(--tdi-green-text);
}
.req-row__status--pending {
    background: var(--tdi-amber-bg);
    color: var(--tdi-amber-text);
}
.req-row__status--rejected {
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
}
.req-row__status--missing {
    background: var(--tdi-slate-bg);
    color: #6b7280;
}
.req-row__remarks {
    font-size: 0.78rem;
    color: var(--tdi-red-text);
    margin-top: 0.6rem;
    background: var(--tdi-red-bg);
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
}
.req-row__notes-readonly {
    font-size: 0.78rem;
    color: #4b5563;
    margin-top: 0.6rem;
    background: #f9fafb;
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
}
.req-card__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f3f9;
}
.req-card__file {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    color: #4b5563;
    min-width: 0;
}
.req-card__file span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.req-card__file--empty {
    color: #9ca3af;
    font-style: italic;
}
.req-card__submitted {
    color: #9ca3af;
    font-style: normal;
    flex-shrink: 0;
}
.req-card__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}
.req-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.45rem 0.8rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s;
}
.req-btn--ghost {
    background: #eef1fc;
    color: var(--tdi-blue);
}
.req-btn--ghost:hover {
    background: #dde4fb;
}
.req-btn--primary {
    background: var(--tdi-blue);
    color: #fff;
}
.req-btn--primary:hover {
    background: #1535a8;
}
.req-card__expand {
    margin-top: 0.9rem;
    padding-top: 0.9rem;
    border-top: 1px solid #f1f3f9;
}
.reqs__empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #9ca3af;
    font-size: 0.9rem;
    padding: 1.5rem;
    background: #f7f9fd;
    border-radius: 12px;
}
.empty-note {
    color: #9ca3af;
    font-size: 0.85rem;
    padding: 1.25rem;
    background: #f7f9fd;
    border-radius: 12px;
    text-align: center;
}

/* Shared file/upload controls (requirements + justification) */
.req-row__file {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}
.file-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--tdi-blue);
    text-decoration: none;
    background: #eef1fc;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
}
.file-link:hover {
    background: #e0e7fb;
}
.delete-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: none;
    background: none;
    color: var(--tdi-red-text);
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.3rem 0.4rem;
}
.delete-btn:hover:not(:disabled) {
    text-decoration: underline;
}
.delete-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.req-row__notes {
    margin-top: 0.6rem;
}
.req-row__notes textarea {
    width: 100%;
    resize: vertical;
    border: 1.5px solid var(--tdi-border);
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
    font-size: 0.8rem;
    font-family: inherit;
    background: #fff;
    color: #374151;
    color-scheme: light;
}
.req-row__notes textarea::placeholder {
    color: #9ca3af;
}
.req-row__notes textarea:focus {
    outline: none;
    border-color: var(--tdi-blue);
}
.req-row__upload {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    flex-wrap: wrap;
}
.upload-input {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: 1.5px dashed #d1d5db;
    border-radius: 8px;
    padding: 0.45rem 0.75rem;
    font-size: 0.78rem;
    color: #4b5563;
    cursor: pointer;
    max-width: 260px;
    transition:
        border-color 0.15s,
        background 0.15s;
}
.upload-input:hover {
    border-color: var(--tdi-blue);
    background: #f7f9fd;
}
.upload-input span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.upload-input input {
    display: none;
}
.upload-btn {
    border: none;
    background: var(--tdi-blue);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s;
}
.upload-btn:hover:not(:disabled) {
    background: #1535a8;
}
.upload-btn:disabled {
    background: #c7d2fe;
    cursor: not-allowed;
}
.req-row__error {
    font-size: 0.74rem;
    color: var(--tdi-red-text);
    margin-top: 0.6rem;
}

/* Absence Justification */
.justification h2 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.5rem;
}
.justification__banner {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
    border-radius: 12px;
    padding: 0.9rem 1.1rem;
    margin-bottom: 1.25rem;
}
.justification__banner-title {
    font-weight: 700;
    font-size: 0.88rem;
}
.justification__banner-sub {
    font-size: 0.8rem;
    margin-top: 0.15rem;
    color: #b91c1c;
}

/* Resource speakers */
.speakers h2,
.docs h2 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.4rem;
}
.speakers__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.85rem;
}
.speaker-card {
    background: #f7f9fd;
    border: 1px solid var(--tdi-border);
    border-radius: 12px;
    padding: 1rem;
}
.speaker-card__avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #eef1fc;
    color: var(--tdi-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.6rem;
}
.speaker-card__name {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--tdi-navy);
}
.speaker-card__role {
    font-size: 0.74rem;
    color: var(--tdi-slate);
    margin-top: 0.15rem;
}
.speaker-card__topic,
.speaker-card__date {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.74rem;
    color: #4b5563;
    margin-top: 0.45rem;
}

/* Supporting Documents */
.docs__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0.85rem;
}
.doc-card {
    background: #f7f9fd;
    border: 1px solid var(--tdi-border);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.doc-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.doc-card__type {
    display: inline-flex;
    font-size: 0.66rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--tdi-blue);
    background: #eef1fc;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
}
.doc-card__series {
    font-size: 0.68rem;
    font-weight: 700;
    color: #9ca3af;
}
.doc-card__subject {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--tdi-navy);
    line-height: 1.3;
}
.doc-card__meta {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.74rem;
    color: var(--tdi-slate);
}
.doc-card__meta span {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.doc-card__link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--tdi-blue);
    text-decoration: none;
    margin-top: 0.2rem;
}
.doc-card__link:hover {
    text-decoration: underline;
}

/* ══════════════════════════════════════════
   CERTIFICATES
══════════════════════════════════════════ */
.certs__header {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}
.certs__title-group {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}
.certs__icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #1d3fc4 0%, #4f46e5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.certs h2 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.2rem;
}
.certs__sub {
    color: var(--tdi-slate);
    font-size: 0.83rem;
    margin: 0;
}

.cert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}
.cert-card {
    position: relative;
    background: #fff;
    border-radius: 14px;
    padding: 1.1rem;
    border: 1px solid var(--tdi-border);
    overflow: hidden;
}
.cert-card__ribbon {
    position: absolute;
    top: 12px;
    right: -28px;
    transform: rotate(45deg);
    width: 100px;
    text-align: center;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 3px 0;
    text-transform: uppercase;
}
.cert-card__ribbon--issued {
    background: var(--tdi-green-bg);
    color: var(--tdi-green-text);
}
.cert-card__ribbon--pending {
    background: var(--tdi-amber-bg);
    color: var(--tdi-amber-text);
}
.cert-card__ribbon--revoked {
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
}
.cert-card__top {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.cert-card__emoji {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cert-card__type {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--tdi-navy);
    line-height: 1.3;
}
.cert-card__number {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 0.15rem;
    font-family: monospace;
}
.cert-card__meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.76rem;
    color: #4b5563;
    margin-bottom: 0.75rem;
}
.cert-card__uploader {
    color: #9ca3af;
    font-style: italic;
}
.cert-card__remarks {
    font-size: 0.75rem;
    color: var(--tdi-slate);
    background: #f9fafb;
    border-radius: 8px;
    padding: 0.4rem 0.6rem;
    margin-bottom: 0.75rem;
}
.cert-card__no-file {
    font-size: 0.75rem;
    color: #d1d5db;
    font-style: italic;
}
.cert-card__actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.cert-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
}
.cert-btn--view {
    background: #eef1fc;
    color: var(--tdi-blue);
}
.cert-btn--view:hover {
    background: #dde4fb;
}
.cert-btn--delete {
    background: var(--tdi-red-bg);
    color: var(--tdi-red-text);
}
.cert-btn--delete:hover:not(:disabled) {
    background: #fee2e2;
}
.cert-btn--delete:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.certs__empty {
    text-align: center;
    padding: 2.25rem;
    background: #f7f9fd;
    border-radius: 14px;
    border: 1.5px dashed var(--tdi-border);
    margin-bottom: 1.75rem;
}
.certs__empty-icon {
    display: flex;
    justify-content: center;
    margin-bottom: 0.75rem;
}
.certs__empty-title {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--tdi-navy);
    margin-bottom: 0.25rem;
}
.certs__empty-sub {
    font-size: 0.8rem;
    color: #9ca3af;
}

.cert-upload {
    background: linear-gradient(135deg, #1d3fc4 0%, #3730a3 100%);
    border-radius: 14px;
    padding: 1.35rem;
}
.cert-upload__header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.cert-upload__title {
    font-size: 0.9rem;
    font-weight: 800;
    color: #fff;
}
.cert-upload__hint {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.6);
}
.cert-upload__row {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    flex-wrap: wrap;
}
.cert-upload__field {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    flex: 1;
    min-width: 180px;
}
.cert-upload__field--file {
    flex: 2;
}
.cert-upload__label {
    font-size: 0.72rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.75);
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.cert-upload__select {
    background: rgba(255, 255, 255, 0.12);
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    border-radius: 10px;
    padding: 0.5rem 0.75rem;
    font-size: 0.82rem;
    font-family: inherit;
    cursor: pointer;
    appearance: none;
    color-scheme: dark;
}
.cert-upload__select:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.6);
}
.cert-upload__select option {
    background: #1d3fc4;
}
.cert-file-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1.5px dashed rgba(255, 255, 255, 0.4);
    color: rgba(255, 255, 255, 0.85);
    border-radius: 10px;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    cursor: pointer;
    transition:
        background 0.15s,
        border-color 0.15s;
    overflow: hidden;
}
.cert-file-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.7);
}
.cert-file-btn span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cert-file-btn input {
    display: none;
}
.cert-upload__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #fff;
    color: var(--tdi-blue);
    font-weight: 800;
    font-size: 0.82rem;
    border: none;
    border-radius: 10px;
    padding: 0.55rem 1.25rem;
    cursor: pointer;
    white-space: nowrap;
    transition:
        background 0.15s,
        opacity 0.15s;
    flex-shrink: 0;
}
.cert-upload__btn:hover:not(:disabled) {
    background: #eef1fc;
}
.cert-upload__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.cert-upload__error {
    font-size: 0.78rem;
    color: #fca5a5;
    margin-top: 0.6rem;
    font-weight: 600;
}
.cert-type-warning {
    font-size: 0.75rem;
    color: #fde68a;
    margin-top: 0.35rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .summary__cards {
        grid-template-columns: 1fr;
    }
    .speakers__grid {
        grid-template-columns: 1fr;
    }
    .cert-grid {
        grid-template-columns: 1fr;
    }
    .cert-upload__row {
        flex-direction: column;
    }
    .cert-upload__btn {
        width: 100%;
        justify-content: center;
    }
    .tab-content > section {
        padding: 1.25rem;
    }
    .req-card__bottom {
        flex-direction: column;
        align-items: stretch;
    }
    .req-card__actions {
        justify-content: stretch;
    }
    .req-btn {
        flex: 1;
        justify-content: center;
    }
}
</style>
