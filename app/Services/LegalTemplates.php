<?php
declare(strict_types=1);

namespace Alien\Services;

use Alien\Core\Settings;
use Alien\Payments\Registry;

final class LegalTemplates
{
    public const PAGES = [
        'privacy-policy' => 'Privacy Policy',
        'cookie-policy' => 'Cookie Policy',
        'termini-e-condizioni' => 'Termini e condizioni di vendita',
        'resi-e-recesso' => 'Resi e diritto di recesso',
        'spedizioni-e-resi' => 'Spedizioni e consegne',
        'informazioni-legali' => 'Informazioni legali',
    ];

    public static function defaults(): array
    {
        return [
            'company' => (string)Settings::get('store_name', ''), 'legal_form' => '', 'address' => '', 'vat' => '', 'tax_code' => '',
            'email' => (string)Settings::get('store_email', ''), 'pec' => '', 'phone' => '', 'registry' => '',
            'sells_to' => 'consumers', 'withdrawal_days' => 14, 'return_shipping' => 'customer', 'refund_days' => 14,
            'ship_min' => 2, 'ship_max' => 5, 'warranty_months' => 24, 'excluded_custom' => 0, 'ship_countries' => '',
            'hosting' => '', 'newsletter' => 0, 'retention_years' => 10,
        ];
    }

    public static function profile(): array
    {
        return array_merge(self::defaults(), Settings::json('legal_profile'));
    }

    public static function tools(): array
    {
        $tools = [];
        foreach (Registry::enabled() as $g) {
            if (in_array($g->id(), ['stripe', 'paypal', 'mollie'], true)) {
                $tools[] = ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'mollie' => 'Mollie'][$g->id()];
            }
        }
        return ['payments' => $tools, 'analytics' => Analytics::id() !== ''];
    }

    public static function build(array $d, string $locale): array
    {
        $en = $locale === 'en';
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $site = $e(parse_url(url(), PHP_URL_HOST));
        $company = $e($d['company']);
        $ident = $company . ($d['legal_form'] !== '' ? ' (' . $e($d['legal_form']) . ')' : '')
            . ($d['address'] !== '' ? ', ' . $e($d['address']) : '')
            . ($d['vat'] !== '' ? ($en ? ', VAT no. ' : ', P.IVA ') . $e($d['vat']) : '')
            . ($d['tax_code'] !== '' ? ($en ? ', Tax code ' : ', C.F. ') . $e($d['tax_code']) : '')
            . ($d['registry'] !== '' ? ', ' . $e($d['registry']) : '');
        $contact = '<a href="mailto:' . $e($d['email']) . '">' . $e($d['email']) . '</a>' . ($d['pec'] !== '' ? ' — PEC: ' . $e($d['pec']) : '') . ($d['phone'] !== '' ? ' — ' . $e($d['phone']) : '');
        $tools = self::tools();
        $payments = implode(', ', $tools['payments']);
        $wd = (int)$d['withdrawal_days'];
        $rd = (int)$d['refund_days'];
        $date = date('d/m/Y');
        $retention = (int)$d['retention_years'];
        $returnCost = $d['return_shipping'] === 'seller';
        $disclaimerIt = '<p><em>Ultimo aggiornamento: ' . $date . '.</em></p>';
        $disclaimerEn = '<p><em>Last updated: ' . $date . '.</em></p>';

        if ($en) {
            $pages = [
                'privacy-policy' => '<p>This policy explains how ' . $ident . ' ("we", the data controller) processes personal data of visitors and customers of ' . $site . ' under Regulation (EU) 2016/679 (GDPR).</p>'
                    . '<h2>Controller and contact</h2><p>' . $ident . '. Contact: ' . $contact . '.</p>'
                    . '<h2>Data we collect and why</h2><ul><li><strong>Order data</strong> (name, address, email, phone, items): to process and ship orders and handle returns — legal basis: contract performance.</li>'
                    . '<li><strong>Billing and tax data</strong>: to meet accounting and tax obligations — legal basis: legal obligation. Retained for ' . $retention . ' years.</li>'
                    . '<li><strong>Account data</strong>: to let you track orders — legal basis: contract performance.</li>'
                    . ($tools['analytics'] ? '<li><strong>Anonymous usage statistics</strong> (Google Analytics 4), only with your consent — legal basis: consent.</li>' : '')
                    . ($d['newsletter'] ? '<li><strong>Newsletter</strong>: only with your consent, which you may withdraw at any time.</li>' : '')
                    . '<li><strong>Technical data</strong> (IP address, logs): security and fraud prevention — legitimate interest.</li></ul>'
                    . '<h2>Recipients</h2><p>Data may be shared with: couriers for delivery' . ($payments !== '' ? '; payment providers (' . $e($payments) . ') for payments; we never see or store full card numbers' : '') . '; our accountant and IT/hosting providers' . ($d['hosting'] !== '' ? ' (' . $e($d['hosting']) . ')' : '') . ', acting as processors; public authorities where required by law.</p>'
                    . ($tools['analytics'] ? '<p>Google may transfer data outside the EEA under the safeguards provided by the EU–US Data Privacy Framework and standard contractual clauses.</p>' : '')
                    . '<h2>Your rights</h2><p>You may request access, rectification, erasure, restriction, portability and object to processing by writing to ' . $contact . '. You may lodge a complaint with your national data protection authority.</p>'
                    . '<h2>Retention</h2><p>Order and tax data are kept for ' . $retention . ' years; account data until you delete the account; consent-based data until you withdraw consent.</p>' . $disclaimerEn,
                'cookie-policy' => '<p>This page explains which cookies and similar technologies ' . $site . ' uses.</p>'
                    . '<h2>Technical cookies (always active)</h2><table><thead><tr><th>Name</th><th>Purpose</th><th>Duration</th></tr></thead><tbody>'
                    . '<tr><td>alien_sid</td><td>Session, login and cart</td><td>Session</td></tr><tr><td>as_cart</td><td>Cart item count</td><td>14 days</td></tr><tr><td>as_consent (local storage)</td><td>Remembers your cookie choice</td><td>Until cleared</td></tr></tbody></table>'
                    . ($tools['analytics'] ? '<h2>Analytics cookies (only with consent)</h2><table><thead><tr><th>Name</th><th>Purpose</th><th>Duration</th></tr></thead><tbody><tr><td>_ga, _ga_*</td><td>Google Analytics 4: anonymous statistics on visits and purchases</td><td>2 years</td></tr></tbody></table>' : '<p>We do not use analytics or profiling cookies.</p>')
                    . ($payments !== '' ? '<h2>Third-party cookies at checkout</h2><p>When you pay, ' . $e($payments) . ' may set their own cookies for security and fraud prevention. See their policies.</p>' : '')
                    . '<h2>Managing your choices</h2><p>You can change your choice at any time with the "Cookie preferences" link in the footer, or through your browser settings.</p>' . $disclaimerEn,
                'termini-e-condizioni' => '<p>These terms govern purchases on ' . $site . ' made from ' . $ident . ' ("Seller").</p>'
                    . '<h2>1. Orders and contract</h2><p>Product pages are an invitation to buy. The contract is concluded when we confirm your order by email. We may refuse or cancel orders in case of errors in price or availability, refunding any amount paid.</p>'
                    . '<h2>2. Prices and payment</h2><p>Prices are shown in ' . $e(\Alien\Core\Money::currency()) . ' and include applicable taxes unless stated otherwise' . '. Shipping costs are shown before you confirm the order. Accepted payment methods are shown at checkout' . ($payments !== '' ? ' (' . $e($payments) . ', and others)' : '') . '.</p>'
                    . '<h2>3. Delivery</h2><p>Orders are shipped within ' . (int)$d['ship_min'] . '–' . (int)$d['ship_max'] . ' business days. See the <a href="' . url('pages/spedizioni-e-resi') . '">shipping page</a>.</p>'
                    . '<h2>4. Right of withdrawal</h2><p>Consumers may withdraw within ' . $wd . ' days of receiving the goods, without giving a reason. Details on the <a href="' . url('pages/resi-e-recesso') . '">returns page</a>.</p>'
                    . '<h2>5. Legal guarantee</h2><p>Goods are covered by the legal guarantee of conformity of ' . (int)$d['warranty_months'] . ' months from delivery.</p>'
                    . '<h2>6. Liability</h2><p>The Seller is liable under applicable consumer law; nothing in these terms limits your statutory rights.</p>'
                    . '<h2>7. Privacy</h2><p>Personal data is processed as described in our <a href="' . url('pages/privacy-policy') . '">Privacy Policy</a>.</p>'
                    . '<h2>8. Disputes</h2><p>The contract is governed by the law of the Seller\'s country, without prejudice to mandatory consumer protections of your country of residence. EU consumers may use the online dispute resolution platform: <a href="https://ec.europa.eu/consumers/odr" rel="noopener">ec.europa.eu/consumers/odr</a>.</p>' . $disclaimerEn,
                'resi-e-recesso' => '<h2>Right of withdrawal</h2><p>If you are a consumer you have the right to withdraw from the contract within <strong>' . $wd . ' days</strong> from the day you (or a person you designate) receive the goods, without giving any reason.</p>'
                    . '<h2>How to exercise it</h2><p>Send a clear statement to ' . $contact . ' (you can use the form below) before the period expires. Then send the goods back within ' . $wd . ' days, unused and in their original packaging.</p>'
                    . '<h2>Costs</h2><p>' . ($returnCost ? 'We bear the cost of returning the goods.' : 'The direct cost of returning the goods is borne by the customer.') . '</p>'
                    . '<h2>Refund</h2><p>We refund all payments, including standard delivery costs, within ' . $rd . ' days of receiving your withdrawal notice, using the same payment method. We may withhold the refund until we receive the goods back or you provide proof of return.</p>'
                    . ($d['excluded_custom'] ? '<h2>Exceptions</h2><p>The right of withdrawal does not apply to goods made to your specifications or clearly personalised, to sealed goods unsuitable for return for hygiene reasons once unsealed, or to perishable goods.</p>' : '')
                    . '<h2>Model withdrawal form</h2><p>To: ' . $ident . ' — ' . $contact . '<br>I hereby give notice that I withdraw from my contract of sale of the following goods: ____________<br>Ordered on / received on: ____________<br>Name: ____________<br>Address: ____________<br>Date: ____________</p>' . $disclaimerEn,
                'spedizioni-e-resi' => '<h2>Delivery times</h2><p>Orders are processed and shipped within ' . (int)$d['ship_min'] . '–' . (int)$d['ship_max'] . ' business days from payment confirmation.</p>'
                    . '<h2>Where we ship</h2><p>' . ($d['ship_countries'] !== '' ? $e($d['ship_countries']) : 'The countries available at checkout.') . '</p>'
                    . '<h2>Costs</h2><p>Shipping costs and free-shipping thresholds are shown at checkout before you pay.</p><h2>Damaged parcels</h2><p>Please check the parcel on delivery and report any damage within 48 hours to ' . $contact . '.</p>' . $disclaimerEn,
                'informazioni-legali' => '<h2>Seller details</h2><p>' . $ident . '</p><p>Contact: ' . $contact . '</p>',
            ];
        } else {
            $pages = [
                'privacy-policy' => '<p>La presente informativa descrive come ' . $ident . ' (il "Titolare") tratta i dati personali di visitatori e clienti del sito ' . $site . ', ai sensi del Regolamento (UE) 2016/679 (GDPR).</p>'
                    . '<h2>Titolare e contatti</h2><p>' . $ident . '. Contatti: ' . $contact . '.</p>'
                    . '<h2>Quali dati raccogliamo e perché</h2><ul><li><strong>Dati dell\'ordine</strong> (nome, indirizzo, email, telefono, prodotti): per gestire, spedire e fatturare gli ordini e gestire resi — base giuridica: esecuzione del contratto.</li>'
                    . '<li><strong>Dati fiscali e contabili</strong>: per adempiere agli obblighi di legge — base giuridica: obbligo legale. Conservati per ' . $retention . ' anni.</li>'
                    . '<li><strong>Dati dell\'account</strong>: per consultare lo storico degli ordini — base giuridica: esecuzione del contratto.</li>'
                    . ($tools['analytics'] ? '<li><strong>Statistiche anonime di utilizzo</strong> (Google Analytics 4), solo con il tuo consenso — base giuridica: consenso.</li>' : '')
                    . ($d['newsletter'] ? '<li><strong>Newsletter</strong>: solo con il tuo consenso, revocabile in qualsiasi momento.</li>' : '')
                    . '<li><strong>Dati tecnici</strong> (indirizzo IP, log): sicurezza e prevenzione frodi — legittimo interesse.</li></ul>'
                    . '<h2>Destinatari</h2><p>I dati possono essere comunicati a: corrieri per la consegna' . ($payments !== '' ? '; fornitori di pagamento (' . $e($payments) . '): non vediamo né conserviamo i numeri completi delle carte' : '') . '; commercialista e fornitori IT/hosting' . ($d['hosting'] !== '' ? ' (' . $e($d['hosting']) . ')' : '') . ', in qualità di responsabili del trattamento; autorità pubbliche quando previsto dalla legge.</p>'
                    . ($tools['analytics'] ? '<p>Google può trasferire dati fuori dallo Spazio Economico Europeo con le garanzie del Data Privacy Framework UE–USA e le clausole contrattuali standard.</p>' : '')
                    . '<h2>I tuoi diritti</h2><p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, portabilità e opporti al trattamento scrivendo a ' . $contact . '. Hai diritto di proporre reclamo al Garante per la protezione dei dati personali (<a href="https://www.garanteprivacy.it" rel="noopener">garanteprivacy.it</a>).</p>'
                    . '<h2>Conservazione</h2><p>I dati di ordini e fiscali sono conservati ' . $retention . ' anni; i dati dell\'account fino alla cancellazione dell\'account; i dati basati sul consenso fino alla sua revoca.</p>' . $disclaimerIt,
                'cookie-policy' => '<p>Questa pagina spiega quali cookie e tecnologie simili utilizza il sito ' . $site . '.</p>'
                    . '<h2>Cookie tecnici (sempre attivi)</h2><table><thead><tr><th>Nome</th><th>Finalità</th><th>Durata</th></tr></thead><tbody>'
                    . '<tr><td>alien_sid</td><td>Sessione, accesso e carrello</td><td>Sessione</td></tr><tr><td>as_cart</td><td>Numero di articoli nel carrello</td><td>14 giorni</td></tr><tr><td>as_consent (local storage)</td><td>Ricorda la tua scelta sui cookie</td><td>Fino alla cancellazione</td></tr></tbody></table>'
                    . ($tools['analytics'] ? '<h2>Cookie analitici (solo con consenso)</h2><table><thead><tr><th>Nome</th><th>Finalità</th><th>Durata</th></tr></thead><tbody><tr><td>_ga, _ga_*</td><td>Google Analytics 4: statistiche anonime su visite e acquisti</td><td>2 anni</td></tr></tbody></table>' : '<p>Non utilizziamo cookie di analisi o di profilazione.</p>')
                    . ($payments !== '' ? '<h2>Cookie di terze parti al pagamento</h2><p>Quando paghi, ' . $e($payments) . ' possono impostare cookie propri per sicurezza e prevenzione frodi. Consulta le loro informative.</p>' : '')
                    . '<h2>Gestire le tue scelte</h2><p>Puoi modificare la scelta in qualsiasi momento dal link "Preferenze cookie" nel footer o dalle impostazioni del browser.</p>' . $disclaimerIt,
                'termini-e-condizioni' => '<p>Le presenti condizioni regolano gli acquisti sul sito ' . $site . ' effettuati da ' . $ident . ' ("Venditore").</p>'
                    . '<h2>1. Ordini e conclusione del contratto</h2><p>Le schede prodotto costituiscono un invito a proporre. Il contratto si conclude quando confermiamo l\'ordine via email. Possiamo rifiutare o annullare ordini in caso di errori di prezzo o disponibilità, rimborsando quanto eventualmente pagato.</p>'
                    . '<h2>2. Prezzi e pagamento</h2><p>I prezzi sono espressi in ' . $e(\Alien\Core\Money::currency()) . ' e, salvo diversa indicazione, comprensivi di imposte. Le spese di spedizione sono indicate prima della conferma dell\'ordine. I metodi di pagamento accettati sono mostrati al checkout' . ($payments !== '' ? ' (' . $e($payments) . ' e altri)' : '') . '.</p>'
                    . '<h2>3. Consegna</h2><p>Gli ordini vengono spediti entro ' . (int)$d['ship_min'] . '–' . (int)$d['ship_max'] . ' giorni lavorativi. Vedi la pagina <a href="' . url('pages/spedizioni-e-resi') . '">Spedizioni e consegne</a>.</p>'
                    . '<h2>4. Diritto di recesso</h2><p>Il consumatore può recedere entro ' . $wd . ' giorni dal ricevimento dei beni senza indicarne il motivo. Dettagli nella pagina <a href="' . url('pages/resi-e-recesso') . '">Resi e diritto di recesso</a>.</p>'
                    . '<h2>5. Garanzia legale di conformità</h2><p>I beni sono coperti dalla garanzia legale di conformità di ' . (int)$d['warranty_months'] . ' mesi dalla consegna (artt. 128 ss. Codice del consumo).</p>'
                    . '<h2>6. Responsabilità</h2><p>Il Venditore risponde secondo la normativa a tutela del consumatore; nulla nelle presenti condizioni limita i diritti inderogabili del consumatore.</p>'
                    . '<h2>7. Privacy</h2><p>I dati personali sono trattati come descritto nella <a href="' . url('pages/privacy-policy') . '">Privacy Policy</a>.</p>'
                    . '<h2>8. Legge applicabile e controversie</h2><p>Il contratto è regolato dalla legge italiana, fatte salve le norme imperative del paese di residenza del consumatore. Per le controversie il foro competente è quello di residenza o domicilio del consumatore. Puoi ricorrere alla piattaforma europea di risoluzione online delle controversie: <a href="https://ec.europa.eu/consumers/odr" rel="noopener">ec.europa.eu/consumers/odr</a>.</p>' . $disclaimerIt,
                'resi-e-recesso' => '<h2>Diritto di recesso</h2><p>Se sei un consumatore hai diritto di recedere dal contratto entro <strong>' . $wd . ' giorni</strong> dal giorno in cui tu, o un terzo da te designato, ricevi i beni, senza dover fornire alcuna motivazione.</p>'
                    . '<h2>Come esercitarlo</h2><p>Invia una dichiarazione esplicita a ' . $contact . ' (puoi usare il modulo qui sotto) prima della scadenza del termine. Restituisci poi i beni entro ' . $wd . ' giorni, integri e nella confezione originale.</p>'
                    . '<h2>Costi</h2><p>' . ($returnCost ? 'Le spese di restituzione sono a nostro carico.' : 'Le spese dirette di restituzione dei beni sono a carico del cliente.') . '</p>'
                    . '<h2>Rimborso</h2><p>Rimborsiamo tutti i pagamenti, comprese le spese di consegna standard, entro ' . $rd . ' giorni dalla ricezione della comunicazione di recesso, con lo stesso metodo di pagamento. Possiamo sospendere il rimborso fino al ricevimento dei beni o alla prova della spedizione.</p>'
                    . ($d['excluded_custom'] ? '<h2>Eccezioni</h2><p>Il diritto di recesso non si applica ai beni confezionati su misura o chiaramente personalizzati, ai beni sigillati che non si prestano a essere restituiti per motivi igienici una volta aperti, ai beni deperibili (art. 59 Codice del consumo).</p>' : '')
                    . '<h2>Modulo tipo di recesso</h2><p>A: ' . $ident . ' — ' . $contact . '<br>Con la presente comunico di recedere dal contratto di vendita dei seguenti beni: ____________<br>Ordinato il / ricevuto il: ____________<br>Nome del consumatore: ____________<br>Indirizzo: ____________<br>Data: ____________</p>' . $disclaimerIt,
                'spedizioni-e-resi' => '<h2>Tempi di consegna</h2><p>Gli ordini vengono lavorati e spediti entro ' . (int)$d['ship_min'] . '–' . (int)$d['ship_max'] . ' giorni lavorativi dalla conferma del pagamento.</p>'
                    . '<h2>Dove spediamo</h2><p>' . ($d['ship_countries'] !== '' ? $e($d['ship_countries']) : 'I paesi disponibili al checkout.') . '</p>'
                    . '<h2>Costi</h2><p>Costi di spedizione e soglie di spedizione gratuita sono mostrati al checkout prima del pagamento.</p><h2>Pacco danneggiato</h2><p>Controlla il pacco alla consegna e segnala eventuali danni entro 48 ore a ' . $contact . '.</p>' . $disclaimerIt,
                'informazioni-legali' => '<h2>Dati del venditore</h2><p>' . $ident . '</p><p>Contatti: ' . $contact . '</p>',
            ];
        }
        $titles = $en
            ? ['privacy-policy' => 'Privacy Policy', 'cookie-policy' => 'Cookie Policy', 'termini-e-condizioni' => 'Terms & Conditions', 'resi-e-recesso' => 'Returns and right of withdrawal', 'spedizioni-e-resi' => 'Shipping & delivery', 'informazioni-legali' => 'Legal information']
            : self::PAGES;
        $out = [];
        foreach ($pages as $slug => $html) {
            $out[$slug] = ['title' => $titles[$slug], 'content' => $html];
        }
        return $out;
    }

    public static function publish(array $slugs, array $profile, string $locale): int
    {
        $built = self::build($profile, $locale);
        $n = 0;
        foreach ($slugs as $slug) {
            if (!isset($built[$slug])) {
                continue;
            }
            $row = ['title' => $built[$slug]['title'], 'content' => $built[$slug]['content'], 'is_active' => 1, 'show_in_footer' => 1, 'updated_at' => now()];
            $existing = \Alien\Core\DB::row("SELECT id FROM pages WHERE type = 'page' AND slug = ?", [$slug]);
            if ($existing) {
                \Alien\Core\DB::update('pages', $row, 'id = ?', [$existing['id']]);
            } else {
                \Alien\Core\DB::insert('pages', $row + ['type' => 'page', 'slug' => $slug, 'excerpt' => '', 'seo_title' => '', 'seo_description' => '', 'show_in_menu' => 0, 'created_at' => now()]);
            }
            $n++;
        }
        Settings::set('legal_done', 1);
        return $n;
    }
}
