module.exports = {
  ci: {
    collect: {
      url: [
        'https://media-lab-starter-kit.localdev/',
        'https://media-lab-starter-kit.localdev/beispiel-seite/',
        'https://media-lab-starter-kit.localdev/blog/',
      ],
      numberOfRuns: 3,
      settings: {
        // Beide Presets prüfen – Mobile ist strenger (Google-Standard)
        preset: 'desktop',
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
        // Throttling: simulate 4G (Lighthouse Standard)
        throttlingMethod: 'simulate',
      },
    },
    assert: {
      preset: 'lighthouse:no-pwa',
      assertions: {

        // ── Lighthouse Score-Kategorien ───────────────────────────────────────
        'categories:performance':    ['error', { minScore: 0.9  }],  // ≥ 90
        'categories:accessibility':  ['error', { minScore: 0.95 }],  // ≥ 95
        'categories:best-practices': ['error', { minScore: 0.95 }],  // ≥ 95
        'categories:seo':            ['error', { minScore: 0.95 }],  // ≥ 95

        // ── Core Web Vitals (Google „Good"-Schwellenwerte) ────────────────────
        //
        // LCP – Largest Contentful Paint
        //   Good:   ≤ 2500ms
        //   Needs:  ≤ 4000ms
        //   Poor:    > 4000ms
        'largest-contentful-paint': ['error', { maxNumericValue: 2500 }],

        // CLS – Cumulative Layout Shift
        //   Good:   ≤ 0.10
        //   Needs:  ≤ 0.25
        //   Poor:    > 0.25
        'cumulative-layout-shift': ['error', { maxNumericValue: 0.1 }],

        // INP – Interaction to Next Paint (ersetzt FID seit März 2024)
        //   Good:   ≤ 200ms
        //   Needs:  ≤ 500ms
        //   Poor:    > 500ms
        // Lighthouse misst INP als TBT (Total Blocking Time) – gutes Proxy-Metrik
        'total-blocking-time': ['error', { maxNumericValue: 200 }],

        // ── Weitere Ladezeit-Metriken ─────────────────────────────────────────
        //
        // FCP – First Contentful Paint
        //   Good: ≤ 1800ms
        'first-contentful-paint': ['error', { maxNumericValue: 1800 }],

        // FID-Proxy: Time to Interactive
        //   Good: ≤ 3800ms
        'interactive': ['warn', { maxNumericValue: 3800 }],

        // Speed Index
        'speed-index': ['warn', { maxNumericValue: 3400 }],

        // ── Ressourcen-Qualität ───────────────────────────────────────────────
        // Keine Bilder ohne width/height (CLS)
        'unsized-images': ['error', { maxLength: 0 }],

        // Keine render-blocking Ressourcen
        'render-blocking-resources': ['warn', { maxLength: 0 }],

        // HTTPS erzwungen
        'is-on-https': ['error', { minScore: 1 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
};
