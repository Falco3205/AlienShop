# Font dei temi

Font da Google Fonts, **self-hosted** (file `.woff2` serviti dal tuo stesso server: nessuna richiesta a Google, nessun dato dei visitatori condiviso, compatibile con la CSP). Tutti con licenza **SIL Open Font License 1.1**, che consente l'uso commerciale e la ridistribuzione insieme al software; i testi delle licenze sono in `licenses/`.

| Tema | Font |
|---|---|
| Aurora | Plus Jakarta Sans |
| Boutique | Cormorant Garamond, Jost |
| Brutalist | Space Grotesk, Space Mono |
| Luxe | Cinzel, Montserrat |
| Midnight | Sora |
| Minimal | Inter |
| Nature | Fraunces, Nunito Sans |
| Pastel | Nunito |
| Tech | JetBrains Mono, Inter |
| Vivid | Outfit |

Sono sottoinsiemi *latin* (coprono l'italiano e le principali lingue europee occidentali) in versione variabile, scaricati dal pacchetto Fontsource. `Themes::build` copia in `public/assets/fonts/` solo quelli usati dal tema attivo. Per aggiungerne uno: metti il `.woff2` qui, la licenza in `licenses/`, e dichiara `@font-face` con `url(fonts/nome.woff2)` nel `style.css` del tema.
