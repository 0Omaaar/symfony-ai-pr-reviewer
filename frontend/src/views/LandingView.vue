<script setup lang="ts">
import { useRouter } from "vue-router";

const router = useRouter();

function goToLogin() {
  void router.push({ name: "login" });
}

function scrollTo(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: "smooth" });
}
</script>

<template>
  <div class="landing">

    <!-- ─── Navbar ─────────────────────────────────────────────── -->
    <header class="navbar">
      <div class="wrap navbar-inner">
        <a class="brand" href="/" aria-label="autoPMR home">
          <div class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
              <path d="M12 2C10.9 2 10 2.9 10 4V5C10 5.55 9.55 6 9 6S8 5.55 8 5V4C8 1.79 9.79 0 12 0S16 1.79 16 4V5C16 5.55 15.55 6 15 6S14 5.55 14 5V4C14 2.9 13.1 2 12 2ZM12 22C10.9 22 10 21.1 10 20V19C10 18.45 9.55 18 9 18S8 18.45 8 19V20C8 22.21 9.79 24 12 24S16 22.21 16 20V19C16 18.45 15.55 18 15 18S14 18.45 14 19V20C14 21.1 13.1 22 12 22ZM2 12C2 10.9 2.9 10 4 10H5C5.55 10 6 9.55 6 9S5.55 8 5 8H4C1.79 8 0 9.79 0 12S1.79 16 4 16H5C5.55 16 6 15.55 6 15S5.55 14 5 14H4C2.9 14 2 13.1 2 12ZM22 12C22 10.9 21.1 10 20 10H19C18.45 10 18 9.55 18 9S18.45 8 19 8H20C22.21 8 24 9.79 24 12S22.21 16 20 16H19C18.45 16 18 15.55 18 15S18.45 14 19 14H20C21.1 14 22 13.1 22 12Z"/>
            </svg>
          </div>
          <span class="brand-name">autoPMR</span>
        </a>

        <nav class="nav-links" aria-label="Page sections">
          <button class="nav-link" @click="scrollTo('features')">Features</button>
          <button class="nav-link" @click="scrollTo('how-it-works')">How it works</button>
          <button class="nav-link" @click="scrollTo('pricing')">Pricing</button>
        </nav>

        <div class="nav-actions">
          <button class="btn-nav-ghost" @click="goToLogin">Log in</button>
          <button class="btn-nav-solid" @click="goToLogin">Get started free</button>
        </div>
      </div>
    </header>

    <!-- ─── Hero ───────────────────────────────────────────────── -->
    <section class="hero">
      <div class="wrap hero-grid">

        <!-- Left: copy -->
        <div class="hero-left">
          <div class="hero-eyebrow">
            <span class="eyebrow-pulse" aria-hidden="true"></span>
            GitHub App · Zero config
          </div>

          <h1 class="hero-h1">
            Know every pull request
            <span class="hero-h1-em">the moment it moves.</span>
          </h1>

          <p class="hero-desc">
            autoPMR watches your repositories and fires instant email alerts whenever a PR is opened, updated, or ready for review — with full diff details, no noise, no setup overhead.
          </p>

          <div class="hero-ctas">
            <button class="btn-hero" @click="goToLogin">
              <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
                <path d="M12 .5a12 12 0 0 0-3.79 23.39c.6.1.82-.26.82-.58v-2.04c-3.34.73-4.04-1.62-4.04-1.62-.55-1.4-1.34-1.77-1.34-1.77-1.1-.76.08-.75.08-.75 1.2.08 1.84 1.25 1.84 1.25 1.08 1.85 2.83 1.32 3.52 1.01.1-.79.42-1.32.77-1.62-2.67-.3-5.47-1.34-5.47-5.94 0-1.31.47-2.39 1.24-3.24-.12-.3-.54-1.53.12-3.18 0 0 1.02-.33 3.33 1.24A11.5 11.5 0 0 1 12 6.32a11.5 11.5 0 0 1 3.03.41c2.3-1.56 3.32-1.24 3.32-1.24.66 1.65.24 2.88.12 3.18.77.85 1.24 1.93 1.24 3.24 0 4.61-2.8 5.63-5.48 5.94.43.38.82 1.12.82 2.26v3.35c0 .32.21.69.82.58A12 12 0 0 0 12 .5Z"/>
              </svg>
              Continue with GitHub
            </button>
            <button class="btn-hero-text" @click="scrollTo('how-it-works')">
              See how it works <span aria-hidden="true">→</span>
            </button>
          </div>

          <p class="hero-footnote">Free to start · No credit card required</p>
        </div>

        <!-- Right: live notification feed -->
        <div class="hero-right" aria-hidden="true">
          <!-- Titlebar shell -->
          <div class="feed-shell">
            <div class="feed-bar">
              <span class="feed-dots"><i></i><i></i><i></i></span>
              <span class="feed-label">autoPMR · live feed</span>
              <span class="feed-live"><span class="live-dot"></span>live</span>
            </div>
            <div class="feed-body">

              <!-- Event 1: opened -->
              <div class="feed-event fe-active">
                <div class="fe-header">
                  <span class="fe-badge fe-open">opened</span>
                  <span class="fe-repo">acme-corp / api-service</span>
                  <span class="fe-prn">#47</span>
                  <span class="fe-time">just now</span>
                </div>
                <p class="fe-title">feat: rate-limit middleware for /v2 endpoints</p>
                <div class="fe-diff">
                  <span class="fd fd-del">- // TODO: add rate limiting</span>
                  <span class="fd fd-add">+ if (requests &gt; limit) throw new HttpException(429);</span>
                  <span class="fd fd-add">+ res.setHeader('X-RateLimit-Reset', resetAt);</span>
                </div>
                <div class="fe-foot">
                  <span class="fe-branch">main ← feature/rate-limit</span>
                  <span class="fe-stat fe-add">+18</span>
                  <span class="fe-stat fe-del">−3</span>
                  <span class="fe-email-sent">↗ 1 email sent</span>
                </div>
              </div>

              <!-- Event 2: ready_for_review -->
              <div class="feed-event">
                <div class="fe-header">
                  <span class="fe-badge fe-review">ready for review</span>
                  <span class="fe-repo">acme-corp / frontend</span>
                  <span class="fe-prn">#112</span>
                  <span class="fe-time">3m ago</span>
                </div>
                <p class="fe-title">chore: migrate dashboard charts to Recharts</p>
                <div class="fe-foot">
                  <span class="fe-branch">main ← refactor/charts</span>
                  <span class="fe-stat fe-add">+241</span>
                  <span class="fe-stat fe-del">−189</span>
                  <span class="fe-email-sent">↗ 2 emails sent</span>
                </div>
              </div>

              <!-- Event 3: closed/merged -->
              <div class="feed-event">
                <div class="fe-header">
                  <span class="fe-badge fe-merged">merged</span>
                  <span class="fe-repo">acme-corp / infra</span>
                  <span class="fe-prn">#29</span>
                  <span class="fe-time">11m ago</span>
                </div>
                <p class="fe-title">fix: increase ECS task memory to 2GB</p>
                <div class="fe-foot">
                  <span class="fe-branch">main ← fix/ecs-memory</span>
                  <span class="fe-stat fe-add">+4</span>
                  <span class="fe-stat fe-del">−1</span>
                  <span class="fe-email-sent fe-muted">↗ 3 emails sent</span>
                </div>
              </div>

              <!-- Event 4: synchronize -->
              <div class="feed-event fe-dim">
                <div class="fe-header">
                  <span class="fe-badge fe-sync">updated</span>
                  <span class="fe-repo">acme-corp / api-service</span>
                  <span class="fe-prn">#46</span>
                  <span class="fe-time">24m ago</span>
                </div>
                <p class="fe-title">fix: validate pagination params on /v2/users</p>
                <div class="fe-foot">
                  <span class="fe-branch">main ← fix/user-pagination</span>
                  <span class="fe-stat fe-add">+7</span>
                  <span class="fe-stat fe-del">−2</span>
                  <span class="fe-email-sent fe-muted">↗ 1 email sent</span>
                </div>
              </div>

            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- ─── Stats strip ─────────────────────────────────────────── -->
    <div class="stats-strip">
      <div class="wrap stats-inner">
        <div class="stat-item">
          <span class="stat-val">&lt;&nbsp;100ms</span>
          <span class="stat-label">average alert delivery</span>
        </div>
        <span class="stat-div" aria-hidden="true"></span>
        <div class="stat-item">
          <span class="stat-val">6</span>
          <span class="stat-label">PR event types supported</span>
        </div>
        <span class="stat-div" aria-hidden="true"></span>
        <div class="stat-item">
          <span class="stat-val">0</span>
          <span class="stat-label">config files required</span>
        </div>
        <span class="stat-div" aria-hidden="true"></span>
        <div class="stat-item">
          <span class="stat-val">1-click</span>
          <span class="stat-label">GitHub App install</span>
        </div>
      </div>
    </div>

    <!-- ─── Trust bar ──────────────────────────────────────────── -->
    <div class="trust-bar">
      <div class="wrap trust-inner">
        <span class="trust-label">Built for engineering teams who ship fast</span>
        <span class="trust-div" aria-hidden="true"></span>
        <span class="trust-tag">Async-first</span>
        <span class="trust-tag">Webhook-driven</span>
        <span class="trust-tag">No polling</span>
        <span class="trust-tag">Open email format</span>
      </div>
    </div>

    <!-- ─── Features ──────────────────────────────────────────── -->
    <section id="features" class="features-section">
      <div class="wrap">
        <div class="features-layout">

          <!-- Sticky heading column -->
          <div class="features-hd">
            <span class="overline">Features</span>
            <h2 class="section-h2">Stop checking<br>GitHub manually.</h2>
            <p class="features-subtext">autoPMR brings the signal to you — the instant it happens.</p>
            <button class="btn-feat-cta" @click="goToLogin">Try it free →</button>
          </div>

          <!-- Feature rows with inline product visuals -->
          <ol class="feat-list">

            <li class="feat-row">
              <span class="feat-n">01</span>
              <div class="feat-body">
                <h3 class="feat-title">Instant PR alerts</h3>
                <p class="feat-desc">Get notified by email the moment a pull request is opened, updated, ready for review, or closed — before anyone else has a chance to miss it.</p>
                <div class="feat-chips">
                  <span class="chip chip-open">opened</span>
                  <span class="chip chip-rev">ready_for_review</span>
                  <span class="chip chip-sync">synchronize</span>
                  <span class="chip chip-closed">closed</span>
                  <span class="chip chip-draft">converted_to_draft</span>
                </div>
              </div>
            </li>

            <li class="feat-row">
              <span class="feat-n">02</span>
              <div class="feat-body">
                <h3 class="feat-title">Full diff in every email</h3>
                <p class="feat-desc">Emails include the head SHA, event type, and a direct link to the repository — everything you need to triage without opening GitHub.</p>
                <div class="feat-meta-block">
                  <div class="fmb-row"><span class="fmb-key">Action</span><span class="fmb-val">opened</span></div>
                  <div class="fmb-row"><span class="fmb-key">Head SHA</span><span class="fmb-val fmb-mono">a3f9c2d841fe</span></div>
                  <div class="fmb-row"><span class="fmb-key">Delivery</span><span class="fmb-val fmb-mono">wh_01j8xk…</span></div>
                  <div class="fmb-row"><span class="fmb-key">Repository</span><span class="fmb-val">acme-corp / api-service</span></div>
                </div>
              </div>
            </li>

            <li class="feat-row">
              <span class="feat-n">03</span>
              <div class="feat-body">
                <h3 class="feat-title">Granular notification filters</h3>
                <p class="feat-desc">Choose exactly which event types trigger alerts. Filter by specific repositories, or subscribe to all. Your inbox, your rules.</p>
                <div class="feat-filter-mock">
                  <div class="ffm-group">
                    <span class="ffm-label">Event types</span>
                    <div class="ffm-checks">
                      <span class="ffm-check ffm-on">✓ opened</span>
                      <span class="ffm-check ffm-on">✓ ready_for_review</span>
                      <span class="ffm-check ffm-off">✗ synchronize</span>
                      <span class="ffm-check ffm-off">✗ closed</span>
                    </div>
                  </div>
                  <div class="ffm-group">
                    <span class="ffm-label">Repositories</span>
                    <div class="ffm-repos">
                      <span class="ffm-repo">acme-corp/api-service ×</span>
                      <span class="ffm-repo">acme-corp/frontend ×</span>
                    </div>
                  </div>
                </div>
              </div>
            </li>

            <li class="feat-row">
              <span class="feat-n">04</span>
              <div class="feat-body">
                <h3 class="feat-title">GitHub App — zero config</h3>
                <p class="feat-desc">Install the GitHub App on any org or personal account in two clicks. autoPMR automatically discovers your repositories — no tokens, no YAML, no CI hooks.</p>
                <div class="feat-repos-mock">
                  <div class="frm-row"><span class="frm-dot frm-active"></span><span class="frm-name">acme-corp / api-service</span><span class="frm-tag">connected</span></div>
                  <div class="frm-row"><span class="frm-dot frm-active"></span><span class="frm-name">acme-corp / frontend</span><span class="frm-tag">connected</span></div>
                  <div class="frm-row"><span class="frm-dot frm-active"></span><span class="frm-name">acme-corp / infra</span><span class="frm-tag">connected</span></div>
                  <div class="frm-row frm-dim"><span class="frm-dot"></span><span class="frm-name">acme-corp / docs</span><span class="frm-tag frm-tag-dim">watching</span></div>
                </div>
              </div>
            </li>

            <li class="feat-row">
              <span class="feat-n">05</span>
              <div class="feat-body">
                <h3 class="feat-title">Multi-installation support</h3>
                <p class="feat-desc">Connect multiple GitHub organizations or personal accounts under one autoPMR login. Monitor everything from a single dashboard.</p>
                <div class="feat-orgs-mock">
                  <div class="fom-org"><span class="fom-avatar">AC</span><div class="fom-info"><span class="fom-name">acme-corp</span><span class="fom-sub">Organization · 12 repos</span></div><span class="fom-status">active</span></div>
                  <div class="fom-org"><span class="fom-avatar fom-av2">JD</span><div class="fom-info"><span class="fom-name">jdoe</span><span class="fom-sub">Personal · 3 repos</span></div><span class="fom-status">active</span></div>
                </div>
              </div>
            </li>

            <li class="feat-row">
              <span class="feat-n">06</span>
              <div class="feat-body">
                <h3 class="feat-title">One-click unsubscribe</h3>
                <p class="feat-desc">Every email contains a one-click unsubscribe link. You can also toggle or fine-tune notifications any time from your account settings.</p>
                <div class="feat-unsub-mock">
                  <p class="fum-text">You are receiving this because your account is linked to the GitHub installation for this repository.</p>
                  <span class="fum-link">Unsubscribe from PR alerts</span>
                </div>
              </div>
            </li>

          </ol>
        </div>
      </div>
    </section>

    <!-- ─── Email preview ──────────────────────────────────────── -->
    <section class="email-section">
      <div class="wrap email-layout">
        <div class="email-hd">
          <span class="overline overline-inv">What lands in your inbox</span>
          <h2 class="section-h2 section-h2-inv">Every alert looks exactly like this.</h2>
          <p class="email-subtext">Formatted, readable, and actionable — no raw JSON, no webhook noise.</p>
        </div>
        <div class="mail-window" aria-hidden="true">
          <!-- Window titlebar -->
          <div class="mw-titlebar">
            <span class="mw-dots"><i></i><i></i><i></i></span>
            <span class="mw-title">Mail — Inbox (4 unread)</span>
          </div>
          <!-- Window body -->
          <div class="mw-body">
            <!-- Message list -->
            <div class="mw-sidebar">
              <div class="mw-search"><span class="mw-search-placeholder">Search</span></div>

              <div class="mw-msg mw-msg-active">
                <div class="mw-msg-top">
                  <span class="mw-msg-from">autoPMR</span>
                  <span class="mw-msg-time">just now</span>
                </div>
                <p class="mw-msg-subj">[autoPMR] PR #47 opened in api-service</p>
                <p class="mw-msg-preview">feat: rate-limit middleware for /v2...</p>
              </div>

              <div class="mw-msg">
                <div class="mw-msg-top">
                  <span class="mw-msg-from">autoPMR</span>
                  <span class="mw-msg-time">3m</span>
                </div>
                <p class="mw-msg-subj">[autoPMR] PR #112 ready for review</p>
                <p class="mw-msg-preview">chore: migrate dashboard charts...</p>
              </div>

              <div class="mw-msg mw-msg-read">
                <div class="mw-msg-top">
                  <span class="mw-msg-from">autoPMR</span>
                  <span class="mw-msg-time">11m</span>
                </div>
                <p class="mw-msg-subj">[autoPMR] PR #29 merged in infra</p>
                <p class="mw-msg-preview">fix: increase ECS task memory...</p>
              </div>

              <div class="mw-msg mw-msg-read">
                <div class="mw-msg-top">
                  <span class="mw-msg-from">autoPMR</span>
                  <span class="mw-msg-time">24m</span>
                </div>
                <p class="mw-msg-subj">[autoPMR] PR #46 updated</p>
                <p class="mw-msg-preview">fix: validate pagination params...</p>
              </div>
            </div>

            <!-- Open email pane -->
            <div class="mw-pane">
              <div class="mw-email-header">
                <span class="mw-badge mw-badge-open">opened</span>
                <h3 class="mw-email-title">[autoPMR] PR #47 opened in acme-corp/api-service</h3>
                <div class="mw-email-from">
                  <span class="mw-from-avatar">AP</span>
                  <div>
                    <span class="mw-from-name">autoPMR Alerts</span>
                    <span class="mw-from-addr">alerts@autopmr.dev → omar@acme-corp.com</span>
                  </div>
                </div>
              </div>
              <div class="mw-email-body">
                <p class="mw-email-greeting">Hello <strong>mrk</strong>,</p>
                <p class="mw-email-text">A pull request event was received and processed by autoPMR. Here are the details:</p>
                <div class="mw-table">
                  <div class="mw-tr"><span class="mw-td-k">Action</span><span class="mw-td-v">opened</span></div>
                  <div class="mw-tr"><span class="mw-td-k">PR</span><span class="mw-td-v">#47 — feat: rate-limit middleware for /v2 endpoints</span></div>
                  <div class="mw-tr"><span class="mw-td-k">Head SHA</span><span class="mw-td-v mw-mono">a3f9c2d841fe3b0c</span></div>
                  <div class="mw-tr"><span class="mw-td-k">Branch</span><span class="mw-td-v mw-mono">feature/rate-limit → main</span></div>
                  <div class="mw-tr"><span class="mw-td-k">Delivery</span><span class="mw-td-v mw-mono">wh_01j8xk9pqr2m…</span></div>
                </div>
                <div class="mw-cta">
                  <span class="mw-cta-btn">Open Repository →</span>
                </div>
                <p class="mw-email-footer">You are receiving this because your account is linked to the GitHub installation for this repository. <span class="mw-unsub">Unsubscribe from PR alerts</span></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── How it works ──────────────────────────────────────── -->
    <section id="how-it-works" class="how-section">
      <div class="wrap">
        <span class="overline overline-inv">How it works</span>
        <h2 class="section-h2 section-h2-inv">Up and running in under two minutes.</h2>

        <ol class="how-steps">
          <li class="how-step">
            <span class="how-n">01</span>
            <h3 class="how-title">Sign in with GitHub</h3>
            <p class="how-desc">Use your existing GitHub account — no separate password needed. OAuth2 handles authentication securely.</p>
            <div class="how-note">
              <span class="how-note-label">Secure</span>
              No password stored. Session managed via HTTP-only cookies.
            </div>
          </li>
          <li class="how-step">
            <span class="how-n">02</span>
            <h3 class="how-title">Install the GitHub App</h3>
            <p class="how-desc">Grant autoPMR read access to pull request events on the repos or organizations you care about. Revoke access any time from GitHub.</p>
            <div class="how-note">
              <span class="how-note-label">Minimal scope</span>
              Read-only access to pull request events. Nothing else.
            </div>
          </li>
          <li class="how-step">
            <span class="how-n">03</span>
            <h3 class="how-title">Configure your filters</h3>
            <p class="how-desc">Choose which PR events trigger alerts. Filter by event type (opened, updated, merged) and by specific repositories.</p>
            <div class="how-note">
              <span class="how-note-label">Optional</span>
              Default is all events, all repos. Refine any time in settings.
            </div>
          </li>
          <li class="how-step">
            <span class="how-n">04</span>
            <h3 class="how-title">Get notified instantly</h3>
            <p class="how-desc">The moment a PR moves, you get an email with full context — repo name, PR number, event type, head SHA, and a direct link.</p>
            <div class="how-note">
              <span class="how-note-label">&lt; 100ms</span>
              From GitHub webhook to email dispatch, typically under 100ms.
            </div>
          </li>
        </ol>
      </div>
    </section>

    <!-- ─── Pricing ────────────────────────────────────────────── -->
    <section id="pricing" class="pricing-section">
      <div class="wrap">
        <span class="overline">Pricing</span>
        <h2 class="section-h2">Simple, transparent pricing.</h2>
        <p class="pricing-sub">Start free, upgrade when your team grows.</p>

        <div class="plans">
          <div class="plan plan-free">
            <div class="plan-top">
              <span class="plan-tier">Free</span>
              <div class="plan-price">
                <span class="plan-amount">$0</span>
                <span class="plan-per">/month</span>
              </div>
              <p class="plan-tagline">Perfect for personal projects and small teams.</p>
            </div>
            <ul class="plan-feats">
              <li><span class="pf-check">—</span>Up to 3 repositories</li>
              <li><span class="pf-check">—</span>All 6 PR event types</li>
              <li><span class="pf-check">—</span>Email notifications</li>
              <li><span class="pf-check">—</span>Notification event filters</li>
              <li><span class="pf-check">—</span>1 GitHub App installation</li>
              <li><span class="pf-check">—</span>Dashboard &amp; repository browser</li>
            </ul>
            <button class="btn-plan-outline" @click="goToLogin">Get started free</button>
            <p class="plan-note">No credit card · No expiry</p>
          </div>

          <div class="plan plan-pro">
            <div class="plan-top">
              <span class="plan-tier plan-tier-inv">Pro</span>
              <div class="plan-price">
                <span class="plan-amount plan-amount-inv">$9</span>
                <span class="plan-per plan-per-inv">/month</span>
              </div>
              <p class="plan-tagline plan-tagline-inv">For teams that need full coverage across all their repos.</p>
            </div>
            <ul class="plan-feats plan-feats-inv">
              <li><span class="pf-check pf-check-inv">—</span>Unlimited repositories</li>
              <li><span class="pf-check pf-check-inv">—</span>All 6 PR event types</li>
              <li><span class="pf-check pf-check-inv">—</span>Email notifications</li>
              <li><span class="pf-check pf-check-inv">—</span>Granular per-repo filters</li>
              <li><span class="pf-check pf-check-inv">—</span>Multiple GitHub App installations</li>
              <li><span class="pf-check pf-check-inv">—</span>Priority support</li>
            </ul>
            <button class="btn-plan-solid" @click="goToLogin">Start free trial</button>
            <p class="plan-note plan-note-inv">14-day trial · Cancel any time</p>
          </div>
        </div>

        <!-- Comparison note -->
        <div class="pricing-footnote">
          <p>All plans include webhook-driven delivery, idempotent processing, and one-click email unsubscribe. No rate limits on notifications.</p>
        </div>
      </div>
    </section>

    <!-- ─── CTA ────────────────────────────────────────────────── -->
    <section class="cta-section">
      <div class="wrap cta-inner">
        <div class="cta-left">
          <h2 class="cta-h2">Ready to stop missing PRs?</h2>
          <p class="cta-sub">Join in seconds — just sign in with GitHub. No form, no password, no friction.</p>
          <div class="cta-stats">
            <span class="cta-stat"><span class="cta-stat-n">Free</span> to start</span>
            <span class="cta-sep" aria-hidden="true">·</span>
            <span class="cta-stat"><span class="cta-stat-n">&lt;2min</span> setup</span>
            <span class="cta-sep" aria-hidden="true">·</span>
            <span class="cta-stat">No <span class="cta-stat-n">credit card</span></span>
          </div>
        </div>
        <button class="btn-hero btn-hero-lg" @click="goToLogin">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" aria-hidden="true">
            <path d="M12 .5a12 12 0 0 0-3.79 23.39c.6.1.82-.26.82-.58v-2.04c-3.34.73-4.04-1.62-4.04-1.62-.55-1.4-1.34-1.77-1.34-1.77-1.1-.76.08-.75.08-.75 1.2.08 1.84 1.25 1.84 1.25 1.08 1.85 2.83 1.32 3.52 1.01.1-.79.42-1.32.77-1.62-2.67-.3-5.47-1.34-5.47-5.94 0-1.31.47-2.39 1.24-3.24-.12-.3-.54-1.53.12-3.18 0 0 1.02-.33 3.33 1.24A11.5 11.5 0 0 1 12 6.32a11.5 11.5 0 0 1 3.03.41c2.3-1.56 3.32-1.24 3.32-1.24.66 1.65.24 2.88.12 3.18.77.85 1.24 1.93 1.24 3.24 0 4.61-2.8 5.63-5.48 5.94.43.38.82 1.12.82 2.26v3.35c0 .32.21.69.82.58A12 12 0 0 0 12 .5Z"/>
          </svg>
          Get started free
        </button>
      </div>
    </section>

    <!-- ─── Footer ─────────────────────────────────────────────── -->
    <footer class="footer">
      <div class="wrap footer-inner">
        <div class="footer-brand">
          <div class="footer-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13">
              <path d="M12 2C10.9 2 10 2.9 10 4V5C10 5.55 9.55 6 9 6S8 5.55 8 5V4C8 1.79 9.79 0 12 0S16 1.79 16 4V5C16 5.55 15.55 6 15 6S14 5.55 14 5V4C14 2.9 13.1 2 12 2ZM12 22C10.9 22 10 21.1 10 20V19C10 18.45 9.55 18 9 18S8 18.45 8 19V20C8 22.21 9.79 24 12 24S16 22.21 16 20V19C16 18.45 15.55 18 15 18S14 18.45 14 19V20C14 21.1 13.1 22 12 22ZM2 12C2 10.9 2.9 10 4 10H5C5.55 10 6 9.55 6 9S5.55 8 5 8H4C1.79 8 0 9.79 0 12S1.79 16 4 16H5C5.55 16 6 15.55 6 15S5.55 14 5 14H4C2.9 14 2 13.1 2 12ZM22 12C22 10.9 21.1 10 20 10H19C18.45 10 18 9.55 18 9S18.45 8 19 8H20C22.21 8 24 9.79 24 12S22.21 16 20 16H19C18.45 16 18 15.55 18 15S18.45 14 19 14H20C21.1 14 22 13.1 22 12Z"/>
            </svg>
          </div>
          <span>autoPMR</span>
          <span class="footer-sep" aria-hidden="true">·</span>
          <span class="footer-desc">Pull request monitoring for engineering teams.</span>
        </div>
        <nav class="footer-nav" aria-label="Footer links">
          <button class="footer-link" @click="scrollTo('features')">Features</button>
          <button class="footer-link" @click="scrollTo('pricing')">Pricing</button>
          <button class="footer-link" @click="goToLogin">Log in</button>
        </nav>
        <p class="footer-copy">&copy; {{ new Date().getFullYear() }} autoPMR.</p>
      </div>
    </footer>

  </div>
</template>

<style scoped>
/* ─── Base ────────────────────────────────────────────────────── */
.landing {
  min-height: 100vh;
  background: #ffffff;
  font-family: var(--font-sans);
  color: var(--ink-strong);
}

.wrap {
  max-width: 1120px;
  margin: 0 auto;
  padding: 0 32px;
}

.overline {
  display: block;
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 14px;
}
.overline-inv { color: #4d9ab5; }

.section-h2 {
  margin: 0;
  font-size: clamp(2rem, 3.5vw, 2.8rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.15;
  color: var(--ink-strong);
}
.section-h2-inv { color: #e6edf3; }

/* ─── Navbar ─────────────────────────────────────────────────── */
.navbar {
  position: sticky;
  top: 0;
  z-index: 200;
  background: #0d1117;
  border-bottom: 1px solid #21262d;
}
.navbar-inner {
  height: 58px;
  display: flex;
  align-items: center;
}
.brand {
  display: flex;
  align-items: center;
  gap: 9px;
  text-decoration: none;
  margin-right: auto;
}
.brand-mark {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #1c2d3f;
  border: 1px solid #2a4060;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0d7ea4;
}
.brand-name {
  font-size: 0.95rem;
  font-weight: 800;
  color: #e6edf3;
  letter-spacing: -0.01em;
}
.nav-links { display: flex; gap: 2px; margin-right: 20px; }
.nav-link {
  background: none;
  border: none;
  padding: 6px 12px;
  font-size: 0.84rem;
  font-weight: 600;
  color: #8b949e;
  cursor: pointer;
  border-radius: 6px;
  font-family: inherit;
  transition: color 0.15s ease, background 0.15s ease;
}
.nav-link:hover { color: #e6edf3; background: rgba(255,255,255,0.05); }
.nav-actions { display: flex; align-items: center; gap: 8px; }
.btn-nav-ghost {
  background: none;
  border: 1px solid #30363d;
  padding: 6px 14px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #8b949e;
  cursor: pointer;
  border-radius: 6px;
  font-family: inherit;
  transition: color 0.15s, border-color 0.15s;
}
.btn-nav-ghost:hover { color: #e6edf3; border-color: #8b949e; }
.btn-nav-solid {
  background: #0d7ea4;
  border: none;
  padding: 6px 14px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  border-radius: 6px;
  font-family: inherit;
  transition: background 0.15s;
}
.btn-nav-solid:hover { background: #0a6a8c; }

/* ─── Hero ────────────────────────────────────────────────────── */
.hero {
  background: #0d1117;
  background-image: radial-gradient(circle, rgba(255,255,255,0.045) 1px, transparent 1px);
  background-size: 28px 28px;
  padding: 88px 0 96px;
  border-bottom: 1px solid #21262d;
}
.hero-grid {
  display: grid;
  grid-template-columns: 1fr 500px;
  gap: 56px;
  align-items: start;
}
.hero-left { display: flex; flex-direction: column; padding-top: 8px; }
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-mono);
  font-size: 0.7rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #4d9ab5;
  margin-bottom: 24px;
}
.eyebrow-pulse {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #3fb950;
  flex-shrink: 0;
  animation: pulse 2.4s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(63,185,80,0.5); }
  60% { opacity: 0.8; box-shadow: 0 0 0 6px rgba(63,185,80,0); }
}
.hero-h1 {
  margin: 0 0 22px;
  font-size: clamp(2.4rem, 4.5vw, 3.6rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1.1;
  color: #e6edf3;
}
.hero-h1-em {
  display: block;
  color: #8b949e;
  font-weight: 400;
  font-style: italic;
}
.hero-desc {
  margin: 0 0 36px;
  font-size: 1rem;
  line-height: 1.72;
  color: #8b949e;
  max-width: 440px;
}
.hero-ctas {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 18px;
  flex-wrap: wrap;
}
.btn-hero {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  background: #e6edf3;
  border: none;
  padding: 11px 20px;
  font-size: 0.88rem;
  font-weight: 700;
  color: #0d1117;
  cursor: pointer;
  border-radius: 7px;
  font-family: inherit;
  transition: background 0.15s;
  white-space: nowrap;
}
.btn-hero:hover { background: #ffffff; }
.btn-hero-lg { padding: 13px 24px; font-size: 0.92rem; }
.btn-hero-text {
  background: none;
  border: none;
  padding: 11px 4px;
  font-size: 0.88rem;
  font-weight: 700;
  color: #6e7681;
  cursor: pointer;
  font-family: inherit;
  transition: color 0.15s;
}
.btn-hero-text:hover { color: #e6edf3; }
.hero-footnote {
  margin: 0;
  font-family: var(--font-mono);
  font-size: 0.7rem;
  color: #30363d;
  letter-spacing: 0.04em;
}

/* ─── Notification feed widget ───────────────────────────────── */
.feed-shell {
  background: #161b22;
  border: 1px solid #30363d;
  border-radius: 10px;
  overflow: hidden;
  font-family: var(--font-mono);
}
.feed-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #0d1117;
  border-bottom: 1px solid #21262d;
}
.feed-dots { display: flex; gap: 5px; }
.feed-dots i { display: block; width: 10px; height: 10px; border-radius: 50%; background: #30363d; }
.feed-dots i:first-child { background: #f85149; }
.feed-dots i:nth-child(2) { background: #d29922; }
.feed-dots i:last-child { background: #3fb950; }
.feed-label {
  flex: 1;
  color: #6e7681;
  font-size: 0.7rem;
  letter-spacing: 0.06em;
  text-align: center;
}
.feed-live {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.66rem;
  font-weight: 600;
  color: #3fb950;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.live-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #3fb950;
  animation: pulse 1.8s ease-in-out infinite;
}
.feed-body { display: flex; flex-direction: column; }

/* Feed events */
.feed-event {
  padding: 12px 14px;
  border-bottom: 1px solid #21262d;
  display: flex;
  flex-direction: column;
  gap: 7px;
  transition: background 0.12s;
}
.feed-event:last-child { border-bottom: none; }
.fe-active { background: rgba(13,126,164,0.06); }
.fe-dim { opacity: 0.55; }

.fe-header {
  display: flex;
  align-items: center;
  gap: 7px;
  flex-wrap: wrap;
}
.fe-badge {
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  padding: 2px 6px;
  border-radius: 3px;
  flex-shrink: 0;
}
.fe-open   { background: rgba(63,185,80,0.15);  color: #3fb950; border: 1px solid rgba(63,185,80,0.25); }
.fe-review { background: rgba(210,153,34,0.15); color: #d29922; border: 1px solid rgba(210,153,34,0.25); }
.fe-merged { background: rgba(163,113,247,0.15);color: #a371f7; border: 1px solid rgba(163,113,247,0.25); }
.fe-sync   { background: rgba(139,148,158,0.15);color: #8b949e; border: 1px solid rgba(139,148,158,0.25); }

.fe-repo { font-size: 0.72rem; color: #8b949e; }
.fe-prn  { font-size: 0.72rem; color: #4d9ab5; font-weight: 700; }
.fe-time { font-size: 0.68rem; color: #484f58; margin-left: auto; }

.fe-title {
  margin: 0;
  font-family: var(--font-sans);
  font-size: 0.8rem;
  font-weight: 600;
  color: #cdd9e5;
  line-height: 1.35;
}
.fe-dim .fe-title { color: #8b949e; }

.fe-diff {
  display: flex;
  flex-direction: column;
  gap: 1px;
  background: #0d1117;
  border-radius: 4px;
  padding: 7px 10px;
  border: 1px solid #21262d;
}
.fd {
  font-size: 0.68rem;
  line-height: 1.55;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.fd-del { color: #f85149; }
.fd-add { color: #3fb950; }

.fe-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.fe-branch { font-size: 0.68rem; color: #6e7681; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fe-stat {
  font-size: 0.66rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 3px;
}
.fe-stat.fe-add { color: #3fb950; background: rgba(63,185,80,0.12); }
.fe-stat.fe-del { color: #f85149; background: rgba(248,81,73,0.12); }
.fe-email-sent { font-size: 0.68rem; color: #4d9ab5; margin-left: auto; }
.fe-muted { color: #484f58; }

/* ─── Stats strip ─────────────────────────────────────────────── */
.stats-strip {
  background: #0d1117;
  border-bottom: 1px solid #21262d;
  padding: 32px 0;
}
.stats-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}
.stat-item { display: flex; flex-direction: column; gap: 4px; }
.stat-val {
  font-size: clamp(1.6rem, 3vw, 2.4rem);
  font-weight: 800;
  letter-spacing: -0.04em;
  color: #e6edf3;
  line-height: 1;
}
.stat-label {
  font-family: var(--font-mono);
  font-size: 0.68rem;
  color: #6e7681;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.stat-div { width: 1px; height: 48px; background: #21262d; flex-shrink: 0; }

/* ─── Trust bar ───────────────────────────────────────────────── */
.trust-bar {
  background: #f5f2ee;
  border-bottom: 1px solid #e8e3dc;
  padding: 12px 0;
}
.trust-inner {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.trust-label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink-soft);
}
.trust-div { width: 1px; height: 14px; background: #c8c0b5; flex-shrink: 0; }
.trust-tag {
  font-family: var(--font-mono);
  font-size: 0.68rem;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: #8a8278;
}

/* ─── Features ────────────────────────────────────────────────── */
.features-section {
  padding: 100px 0;
  background: #ffffff;
}
.features-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 80px;
  align-items: start;
}
.features-hd {
  position: sticky;
  top: 80px;
  display: flex;
  flex-direction: column;
  gap: 0;
}
.features-subtext {
  margin: 16px 0 24px;
  font-size: 0.88rem;
  line-height: 1.65;
  color: var(--ink-soft);
}
.btn-feat-cta {
  background: none;
  border: 1px solid var(--line-strong);
  padding: 8px 14px;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--ink-body);
  cursor: pointer;
  border-radius: 6px;
  font-family: inherit;
  align-self: flex-start;
  transition: border-color 0.15s, color 0.15s;
}
.btn-feat-cta:hover { border-color: var(--accent); color: var(--accent); }

.feat-list { list-style: none; margin: 0; padding: 0; }
.feat-row {
  display: flex;
  gap: 20px;
  padding: 28px 0;
  border-top: 1px solid var(--line);
}
.feat-row:last-child { border-bottom: 1px solid var(--line); }
.feat-n {
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 500;
  color: var(--ink-faint);
  letter-spacing: 0.06em;
  padding-top: 3px;
  flex-shrink: 0;
  width: 22px;
}
.feat-title {
  margin: 0 0 7px;
  font-size: 0.94rem;
  font-weight: 800;
  color: var(--ink-strong);
}
.feat-desc {
  margin: 0 0 14px;
  font-size: 0.86rem;
  line-height: 1.68;
  color: var(--ink-soft);
}

/* Event type chips */
.feat-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
  font-family: var(--font-mono);
  font-size: 0.66rem;
  font-weight: 500;
  letter-spacing: 0.05em;
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid;
}
.chip-open   { color: #3fb950; background: rgba(63,185,80,0.08);   border-color: rgba(63,185,80,0.25); }
.chip-rev    { color: #d29922; background: rgba(210,153,34,0.08);  border-color: rgba(210,153,34,0.25); }
.chip-sync   { color: #8b949e; background: rgba(139,148,158,0.08); border-color: rgba(139,148,158,0.25); }
.chip-closed { color: #f85149; background: rgba(248,81,73,0.08);   border-color: rgba(248,81,73,0.25); }
.chip-draft  { color: #a371f7; background: rgba(163,113,247,0.08); border-color: rgba(163,113,247,0.25); }

/* Meta block (feature 02) */
.feat-meta-block {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: hidden;
}
.fmb-row {
  display: flex;
  align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid var(--line);
  gap: 16px;
}
.fmb-row:last-child { border-bottom: none; }
.fmb-key {
  font-family: var(--font-mono);
  font-size: 0.68rem;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ink-faint);
  width: 90px;
  flex-shrink: 0;
}
.fmb-val { font-size: 0.82rem; color: var(--ink-body); font-weight: 600; }
.fmb-mono { font-family: var(--font-mono); font-size: 0.78rem; }

/* Filter mock (feature 03) */
.feat-filter-mock {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 12px 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ffm-label {
  font-family: var(--font-mono);
  font-size: 0.66rem;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ink-faint);
  display: block;
  margin-bottom: 7px;
}
.ffm-checks { display: flex; gap: 8px; flex-wrap: wrap; }
.ffm-check {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid;
}
.ffm-on  { color: #3fb950; background: rgba(63,185,80,0.08);   border-color: rgba(63,185,80,0.2); }
.ffm-off { color: #6e7681; background: rgba(139,148,158,0.06); border-color: #30363d; }
.ffm-repos { display: flex; gap: 6px; flex-wrap: wrap; }
.ffm-repo {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  color: #4d9ab5;
  background: rgba(13,126,164,0.08);
  border: 1px solid rgba(13,126,164,0.2);
  padding: 3px 9px;
  border-radius: 4px;
}

/* Repos mock (feature 04) */
.feat-repos-mock {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: hidden;
}
.frm-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-bottom: 1px solid var(--line);
}
.frm-row:last-child { border-bottom: none; }
.frm-row.frm-dim { opacity: 0.5; }
.frm-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #30363d;
  flex-shrink: 0;
}
.frm-dot.frm-active { background: #3fb950; }
.frm-name { font-family: var(--font-mono); font-size: 0.76rem; color: var(--ink-body); flex: 1; }
.frm-tag {
  font-family: var(--font-mono);
  font-size: 0.64rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #3fb950;
  background: rgba(63,185,80,0.08);
  border: 1px solid rgba(63,185,80,0.2);
  padding: 2px 7px;
  border-radius: 3px;
}
.frm-tag.frm-tag-dim { color: #8b949e; background: none; border-color: #30363d; }

/* Orgs mock (feature 05) */
.feat-orgs-mock {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: hidden;
}
.fom-org {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
}
.fom-org:last-child { border-bottom: none; }
.fom-avatar {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #1469a0, #0d90c5);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-family: var(--font-sans);
}
.fom-av2 { background: linear-gradient(135deg, #2d4a6e, #3a6491); }
.fom-info { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.fom-name { font-size: 0.82rem; font-weight: 700; color: var(--ink-strong); }
.fom-sub  { font-family: var(--font-mono); font-size: 0.66rem; color: var(--ink-faint); letter-spacing: 0.03em; }
.fom-status {
  font-family: var(--font-mono);
  font-size: 0.64rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #3fb950;
}

/* Unsubscribe mock (feature 06) */
.feat-unsub-mock {
  background: var(--surface-soft);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 12px 14px;
}
.fum-text {
  margin: 0 0 8px;
  font-size: 0.78rem;
  color: var(--ink-soft);
  line-height: 1.55;
}
.fum-link {
  font-size: 0.76rem;
  color: var(--ink-faint);
  text-decoration: underline;
  cursor: default;
}

/* ─── Email preview section ──────────────────────────────────── */
.email-section {
  background: #0d1117;
  border-top: 1px solid #21262d;
  border-bottom: 1px solid #21262d;
  padding: 96px 0;
}
.email-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 64px;
  align-items: start;
}
.email-hd { padding-top: 8px; }
.email-subtext {
  margin: 16px 0 0;
  font-size: 0.88rem;
  line-height: 1.65;
  color: #6e7681;
}

/* Mail window mockup */
.mail-window {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #333;
  box-shadow:
    0 0 0 1px #1a1a1a,
    0 32px 80px -16px rgba(0,0,0,0.75);
}

.mw-titlebar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  background: #1e1e1e;
  border-bottom: 1px solid #333;
}
.mw-dots { display: flex; gap: 6px; }
.mw-dots i { display: block; width: 12px; height: 12px; border-radius: 50%; }
.mw-dots i:nth-child(1) { background: #ff5f57; }
.mw-dots i:nth-child(2) { background: #febc2e; }
.mw-dots i:nth-child(3) { background: #28c840; }
.mw-title {
  flex: 1;
  text-align: center;
  font-size: 0.76rem;
  font-weight: 500;
  color: #666;
  font-family: var(--font-sans);
  margin-right: 52px;
}

.mw-body {
  display: grid;
  grid-template-columns: 252px 1fr;
  height: 400px;
}

/* Sidebar */
.mw-sidebar {
  background: #1a1a1a;
  border-right: 1px solid #2a2a2a;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.mw-search {
  padding: 8px 10px;
  border-bottom: 1px solid #2a2a2a;
  flex-shrink: 0;
}
.mw-search-placeholder {
  display: block;
  background: #252525;
  border: 1px solid #363636;
  border-radius: 5px;
  padding: 5px 10px;
  font-size: 0.7rem;
  color: #555;
  font-family: var(--font-sans);
}

.mw-msg {
  padding: 10px 14px 10px 20px;
  border-bottom: 1px solid #242424;
  display: flex;
  flex-direction: column;
  gap: 2px;
  position: relative;
  flex-shrink: 0;
}
.mw-msg::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 18px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #0d7ea4;
}
.mw-msg-read::before { display: none; }
.mw-msg-active {
  background: #0d7ea4;
}
.mw-msg-active::before { display: none; }

.mw-msg-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.mw-msg-from {
  font-size: 0.76rem;
  font-weight: 700;
  color: #d0d0d0;
  font-family: var(--font-sans);
}
.mw-msg-active .mw-msg-from { color: #fff; }
.mw-msg-read .mw-msg-from { font-weight: 500; color: #777; }
.mw-msg-time {
  font-size: 0.64rem;
  color: #555;
  font-family: var(--font-sans);
}
.mw-msg-active .mw-msg-time { color: rgba(255,255,255,0.65); }

.mw-msg-subj {
  margin: 0;
  font-size: 0.72rem;
  font-weight: 600;
  color: #b0b0b0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-family: var(--font-sans);
}
.mw-msg-active .mw-msg-subj { color: #fff; }
.mw-msg-read .mw-msg-subj { color: #666; font-weight: 400; }

.mw-msg-preview {
  margin: 0;
  font-size: 0.68rem;
  color: #4a4a4a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-family: var(--font-sans);
}
.mw-msg-active .mw-msg-preview { color: rgba(255,255,255,0.55); }

/* Email pane */
.mw-pane {
  background: #fff;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.mw-email-header {
  padding: 18px 22px 14px;
  border-bottom: 1px solid #e8ecf0;
  flex-shrink: 0;
}
.mw-badge {
  display: inline-block;
  font-family: var(--font-mono);
  font-size: 0.58rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  padding: 2px 7px;
  border-radius: 3px;
  margin-bottom: 7px;
}
.mw-badge-open {
  background: rgba(34,134,58,0.1);
  color: #22863a;
  border: 1px solid rgba(34,134,58,0.25);
}
.mw-email-title {
  margin: 0 0 11px;
  font-size: 0.88rem;
  font-weight: 700;
  color: #1a2333;
  line-height: 1.4;
  font-family: var(--font-sans);
}
.mw-email-from {
  display: flex;
  align-items: center;
  gap: 9px;
}
.mw-from-avatar {
  width: 30px;
  height: 30px;
  border-radius: 7px;
  background: linear-gradient(135deg, #0d7ea4, #0ea5e9);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-family: var(--font-sans);
}
.mw-from-name {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: #2a3a4e;
  font-family: var(--font-sans);
}
.mw-from-addr {
  display: block;
  font-size: 0.66rem;
  color: #8a97aa;
  font-family: var(--font-mono);
}

.mw-email-body {
  padding: 18px 22px 22px;
  flex: 1;
}
.mw-email-greeting {
  margin: 0 0 5px;
  font-size: 0.86rem;
  color: #233a57;
  font-family: var(--font-sans);
}
.mw-email-text {
  margin: 0 0 14px;
  font-size: 0.82rem;
  line-height: 1.6;
  color: #3a4d68;
  font-family: var(--font-sans);
}
.mw-table {
  border: 1px solid #e6ecf5;
  border-radius: 7px;
  overflow: hidden;
  margin-bottom: 16px;
}
.mw-tr {
  display: flex;
  align-items: baseline;
  gap: 10px;
  padding: 8px 11px;
  border-bottom: 1px solid #e6ecf5;
  background: #f7faff;
}
.mw-tr:last-child { border-bottom: none; }
.mw-td-k {
  font-family: var(--font-mono);
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #7f8ea3;
  width: 76px;
  flex-shrink: 0;
}
.mw-td-v { font-size: 0.8rem; color: #13233a; font-weight: 600; line-height: 1.4; font-family: var(--font-sans); }
.mw-mono { font-family: var(--font-mono); font-size: 0.74rem; }

.mw-cta { margin-bottom: 16px; }
.mw-cta-btn {
  display: inline-block;
  background: #0d1117;
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 9px 17px;
  border-radius: 7px;
  cursor: default;
  font-family: var(--font-sans);
}
.mw-email-footer {
  margin: 0;
  font-size: 0.7rem;
  color: #7f8ea3;
  line-height: 1.55;
  font-family: var(--font-sans);
}
.mw-unsub { text-decoration: underline; cursor: default; }

/* ─── How it works ────────────────────────────────────────────── */
.how-section {
  background: #0d1117;
  padding: 96px 0;
}
.how-section .overline-inv { margin-bottom: 14px; }
.how-section .section-h2-inv { margin-bottom: 56px; }

.how-steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0;
}
.how-step {
  padding: 0 32px 0 0;
  border-right: 1px solid #21262d;
}
.how-step:first-child { padding-left: 0; }
.how-step:last-child { border-right: none; padding-right: 0; }
.how-step:not(:first-child) { padding-left: 32px; }

.how-n {
  display: block;
  font-family: var(--font-mono);
  font-size: 0.66rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  color: #4d9ab5;
  margin-bottom: 14px;
}
.how-title {
  margin: 0 0 10px;
  font-size: 0.92rem;
  font-weight: 800;
  color: #e6edf3;
  letter-spacing: -0.01em;
}
.how-desc {
  margin: 0 0 16px;
  font-size: 0.84rem;
  line-height: 1.65;
  color: #6e7681;
}
.how-note {
  font-family: var(--font-mono);
  font-size: 0.7rem;
  color: #484f58;
  line-height: 1.5;
  border-top: 1px solid #21262d;
  padding-top: 12px;
}
.how-note-label {
  display: inline-block;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #4d9ab5;
  margin-bottom: 4px;
  margin-right: 4px;
}

/* ─── Pricing ─────────────────────────────────────────────────── */
.pricing-section {
  background: #f5f2ee;
  padding: 96px 0;
}
.pricing-sub {
  margin: 10px 0 44px;
  font-size: 0.9rem;
  color: var(--ink-soft);
}
.plans {
  display: grid;
  grid-template-columns: 1fr 1fr;
  max-width: 720px;
  border: 1px solid var(--line-strong);
  border-radius: 12px;
  overflow: hidden;
  margin-bottom: 24px;
}
.plan-free { background: #ffffff; padding: 32px 28px; display: flex; flex-direction: column; }
.plan-pro  { background: #0d1117; padding: 32px 28px; display: flex; flex-direction: column; }

.plan-top { margin-bottom: 24px; }
.plan-tier {
  display: block;
  font-family: var(--font-mono);
  font-size: 0.66rem;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-faint);
  margin-bottom: 10px;
}
.plan-tier-inv { color: #4d9ab5; }
.plan-price { display: flex; align-items: baseline; gap: 4px; margin-bottom: 10px; }
.plan-amount {
  font-size: 3rem;
  font-weight: 800;
  letter-spacing: -0.06em;
  color: var(--ink-strong);
  line-height: 1;
}
.plan-amount-inv { color: #e6edf3; }
.plan-per { font-size: 0.82rem; color: var(--ink-faint); font-weight: 600; }
.plan-per-inv { color: #6e7681; }
.plan-tagline { margin: 0; font-size: 0.84rem; color: var(--ink-soft); line-height: 1.55; }
.plan-tagline-inv { color: #6e7681; }

.plan-feats { list-style: none; margin: 0 0 24px; padding: 0; flex: 1; }
.plan-feats li {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.86rem;
  color: var(--ink-body);
  padding: 9px 0;
  border-bottom: 1px solid var(--line);
  font-weight: 600;
}
.plan-feats li:last-child { border-bottom: none; }
.plan-feats-inv li { color: #8b949e; border-bottom-color: #21262d; }
.pf-check { color: var(--ink-faint); font-weight: 400; flex-shrink: 0; }
.pf-check-inv { color: #30363d; }

.btn-plan-outline {
  background: none;
  border: 1px solid var(--line-strong);
  padding: 10px 18px;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--ink-body);
  cursor: pointer;
  border-radius: 7px;
  font-family: inherit;
  margin-bottom: 10px;
  transition: border-color 0.15s, color 0.15s;
}
.btn-plan-outline:hover { border-color: var(--accent); color: var(--accent); }
.btn-plan-solid {
  background: #e6edf3;
  border: none;
  padding: 10px 18px;
  font-size: 0.86rem;
  font-weight: 700;
  color: #0d1117;
  cursor: pointer;
  border-radius: 7px;
  font-family: inherit;
  margin-bottom: 10px;
  transition: background 0.15s;
}
.btn-plan-solid:hover { background: #ffffff; }
.plan-note {
  margin: 0;
  font-family: var(--font-mono);
  font-size: 0.68rem;
  color: var(--ink-faint);
  letter-spacing: 0.04em;
}
.plan-note-inv { color: #484f58; }

.pricing-footnote {
  max-width: 720px;
  padding: 16px 4px 0;
  border-top: 1px solid #e8e3dc;
}
.pricing-footnote p {
  margin: 0;
  font-size: 0.82rem;
  color: var(--ink-soft);
  line-height: 1.6;
}

/* ─── CTA ─────────────────────────────────────────────────────── */
.cta-section {
  background: #0d1117;
  border-top: 1px solid #21262d;
  padding: 80px 0;
}
.cta-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 48px;
  flex-wrap: wrap;
}
.cta-h2 {
  margin: 0 0 10px;
  font-size: clamp(1.6rem, 2.8vw, 2.2rem);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #e6edf3;
}
.cta-sub {
  margin: 0 0 20px;
  font-size: 0.9rem;
  color: #6e7681;
  line-height: 1.6;
}
.cta-stats { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.cta-stat {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  color: #484f58;
  letter-spacing: 0.03em;
}
.cta-stat-n { color: #4d9ab5; font-weight: 700; }
.cta-sep { color: #21262d; }

/* ─── Footer ──────────────────────────────────────────────────── */
.footer {
  background: #f5f2ee;
  border-top: 1px solid #e8e3dc;
  padding: 24px 0;
}
.footer-inner {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}
.footer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.86rem;
  font-weight: 700;
  color: var(--ink-body);
  margin-right: auto;
}
.footer-mark {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  background: #0d1117;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4d9ab5;
}
.footer-sep { color: var(--ink-faint); }
.footer-desc { font-size: 0.78rem; font-weight: 400; color: var(--ink-faint); }
.footer-nav { display: flex; gap: 2px; }
.footer-link {
  background: none;
  border: none;
  padding: 5px 10px;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ink-soft);
  cursor: pointer;
  border-radius: 5px;
  font-family: inherit;
  transition: color 0.12s;
}
.footer-link:hover { color: var(--ink-strong); }
.footer-copy { margin: 0; font-size: 0.76rem; color: var(--ink-faint); }

/* ─── Responsive ──────────────────────────────────────────────── */
@media (max-width: 960px) {
  .hero-grid { grid-template-columns: 1fr; gap: 40px; }
  .hero-right { order: -1; }
  .hero-desc { max-width: 100%; }

  .features-layout { grid-template-columns: 1fr; gap: 36px; }
  .features-hd { position: static; }

  .how-steps { grid-template-columns: 1fr 1fr; }
  .how-step { border-right: none; border-bottom: 1px solid #21262d; padding: 28px 0 !important; }
  .how-step:nth-child(odd) { padding-right: 20px !important; border-right: 1px solid #21262d; }
  .how-step:last-child { border-bottom: none; }

  .email-layout { grid-template-columns: 1fr; gap: 36px; }
  .mw-body { grid-template-columns: 1fr; }
  .mw-sidebar { display: none; }

  .stats-inner { justify-content: flex-start; gap: 32px; flex-wrap: wrap; }
  .stat-div { display: none; }
}

@media (max-width: 640px) {
  .wrap { padding: 0 18px; }
  .nav-links { display: none; }
  .hero { padding: 56px 0 64px; }
  .features-section, .how-section, .email-section, .pricing-section { padding: 64px 0; }

  .how-steps { grid-template-columns: 1fr; }
  .how-step { border-right: none !important; padding-right: 0 !important; border-bottom: 1px solid #21262d; }
  .how-step:last-child { border-bottom: none; }

  .plans { grid-template-columns: 1fr; }

  .cta-inner { flex-direction: column; align-items: flex-start; }
  .footer-inner { flex-direction: column; align-items: flex-start; gap: 14px; }
  .footer-brand { margin-right: 0; }
  .trust-inner { gap: 8px; }
  .trust-div { display: none; }
}
</style>
