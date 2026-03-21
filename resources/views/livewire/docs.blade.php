<div class="max-w-4xl space-y-8">

    {{-- Intro --}}
    <div class="glass-card p-8">
        <h1 class="text-2xl font-bold text-white mb-2">Was ist Tradebot?</h1>
        <p class="text-white/60 text-sm leading-relaxed">
            Tradebot ist ein automatisierter Krypto-Handelsbot. Er liest rund um die Uhr Nachrichten aus konfigurierbaren Quellen,
            bewertet diese mit KI, zieht daraus Handelsentscheidungen und kann diese – je nach Einstellung – automatisch oder
            manuell auf Coinbase ausführen. Das Ziel ist nicht maximaler Gewinn, sondern <span class="text-neon-green">Kapitalerhalt zuerst,
            Rendite zweite Priorität</span>.
        </p>
    </div>

    {{-- Wie funktioniert es --}}
    <div class="glass-card p-8">
        <h2 class="text-lg font-semibold text-white mb-6">Wie funktioniert der Bot?</h2>
        <div class="space-y-4">

            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-neon-blue text-xs font-bold">1</span>
                </div>
                <div>
                    <div class="text-white font-medium text-sm mb-1">Nachrichten sammeln (alle 15 Min.)</div>
                    <p class="text-white/50 text-sm leading-relaxed">
                        Der Scraper lädt RSS-Feeds von konfigurierten Nachrichtenquellen (z.B. CoinDesk, CryptoPotato, Solana Foundation).
                        Jeder Artikel wird per SHA-256 Hash dedupliziert – derselbe Artikel wird nie zweimal verarbeitet.
                    </p>
                </div>
            </div>

            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-neon-blue text-xs font-bold">2</span>
                </div>
                <div>
                    <div class="text-white font-medium text-sm mb-1">Artikel bewerten (KI-Scoring)</div>
                    <p class="text-white/50 text-sm leading-relaxed">
                        Jeder neue Artikel wird von Gemini (primär) oder Claude (Fallback) analysiert. Das Ergebnis ist ein
                        <span class="text-white/80">Sentiment-Score von -1.0 bis +1.0</span> pro Asset:
                        -1.0 = sehr negativ, 0 = neutral, +1.0 = sehr positiv.
                        Außerdem wird ein Signal-Typ vergeben (regulatory, market_move, adoption, technical, macro).
                    </p>
                </div>
            </div>

            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-neon-blue text-xs font-bold">3</span>
                </div>
                <div>
                    <div class="text-white font-medium text-sm mb-1">Analyse-Zyklus (alle 30 Min.)</div>
                    <p class="text-white/50 text-sm leading-relaxed">
                        Alle 30 Minuten wertet Claude die gesammelten Signale der letzten 6 Stunden aus. Dabei sieht er:
                    </p>
                    <ul class="mt-2 space-y-1 text-white/50 text-sm">
                        <li class="flex gap-2"><span class="text-neon-blue shrink-0">•</span> Das aktuelle Portfolio (Cash, gehaltene Coins, Einstiegspreise)</li>
                        <li class="flex gap-2"><span class="text-neon-blue shrink-0">•</span> Aggregierte Sentiment-Scores pro Asset (aus den letzten 6h)</li>
                        <li class="flex gap-2"><span class="text-neon-blue shrink-0">•</span> Handelsvolumen (24h vs. 7-Tage-Durchschnitt)</li>
                        <li class="flex gap-2"><span class="text-neon-blue shrink-0">•</span> Technische Indikatoren: RSI, MACD, SMA20/50, Bollinger Bands, Support/Resistance</li>
                    </ul>
                </div>
            </div>

            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-neon-blue text-xs font-bold">4</span>
                </div>
                <div>
                    <div class="text-white font-medium text-sm mb-1">Handelsentscheidung</div>
                    <p class="text-white/50 text-sm leading-relaxed">
                        Claude gibt eine Entscheidung (buy / sell / hold) mit einem
                        <span class="text-white/80">Confidence-Wert (0–100%)</span> und einem Euro-Betrag zurück.
                        Entscheidungen unter 60% Confidence werden automatisch verworfen.
                    </p>
                </div>
            </div>

            <div class="flex gap-4">
                <div class="w-8 h-8 rounded-full bg-neon-blue/20 border border-neon-blue/30 flex items-center justify-center shrink-0 mt-0.5">
                    <span class="text-neon-blue text-xs font-bold">5</span>
                </div>
                <div>
                    <div class="text-white font-medium text-sm mb-1">Ausführung</div>
                    <p class="text-white/50 text-sm leading-relaxed">
                        Je nach Modus wird die Entscheidung entweder <span class="text-neon-blue">simuliert (Paper)</span>
                        oder <span class="text-yellow-400">wirklich auf Coinbase ausgeführt (Live)</span>.
                        Im manuellen Modus (Auto-Trade aus) erscheint die Entscheidung im Dashboard zur Genehmigung.
                    </p>
                </div>
            </div>

        </div>
    </div>

    {{-- Technische Indikatoren --}}
    <div class="glass-card p-8">
        <h2 class="text-lg font-semibold text-white mb-4">Technische Indikatoren – was bedeuten sie?</h2>
        <div class="space-y-4 text-sm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <div class="text-white font-medium mb-2">RSI (Relative Strength Index)</div>
                    <p class="text-white/50 leading-relaxed">Misst ob ein Asset überkauft oder überverkauft ist (stündlich, 14 Perioden).</p>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between"><span class="text-neon-red">RSI &gt; 70</span><span class="text-white/40">überkauft → Kurskorrektur möglich</span></div>
                        <div class="flex justify-between"><span class="text-white/50">RSI 40–60</span><span class="text-white/40">neutral</span></div>
                        <div class="flex justify-between"><span class="text-neon-green">RSI &lt; 30</span><span class="text-white/40">überverkauft → Erholung möglich</span></div>
                    </div>
                </div>

                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <div class="text-white font-medium mb-2">MACD</div>
                    <p class="text-white/50 leading-relaxed">Zeigt Momentum und Trendwechsel (stündlich, 12/26/9 EMA).</p>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between"><span class="text-neon-green">Histogramm steigt</span><span class="text-white/40">bullisches Momentum</span></div>
                        <div class="flex justify-between"><span class="text-neon-green">Bullish Cross</span><span class="text-white/40">MACD kreuzt Signal von unten</span></div>
                        <div class="flex justify-between"><span class="text-neon-red">Bearish Cross</span><span class="text-white/40">MACD kreuzt Signal von oben</span></div>
                    </div>
                </div>

                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <div class="text-white font-medium mb-2">SMA 20 / SMA 50</div>
                    <p class="text-white/50 leading-relaxed">Gleitende Durchschnitte (täglich). Zeigen den mittelfristigen Trend.</p>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between"><span class="text-neon-green">Golden Cross</span><span class="text-white/40">SMA20 über SMA50 → bullish</span></div>
                        <div class="flex justify-between"><span class="text-neon-red">Death Cross</span><span class="text-white/40">SMA20 unter SMA50 → bearish</span></div>
                    </div>
                </div>

                <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                    <div class="text-white font-medium mb-2">Bollinger Bands</div>
                    <p class="text-white/50 leading-relaxed">Zeigen Volatilität und relative Kursposition (täglich, 20 Perioden, 2σ).</p>
                    <div class="mt-3 space-y-1">
                        <div class="flex justify-between"><span class="text-neon-red">Above Upper Band</span><span class="text-white/40">überdehnt, Vorsicht bei Kauf</span></div>
                        <div class="flex justify-between"><span class="text-white/50">Mid</span><span class="text-white/40">neutral, im normalen Bereich</span></div>
                        <div class="flex justify-between"><span class="text-neon-green">Below Lower Band</span><span class="text-white/40">mögliche Umkehrzone</span></div>
                    </div>
                </div>
            </div>

            <div class="bg-white/[0.03] rounded-xl p-4 border border-white/[0.06]">
                <div class="text-white font-medium mb-2">Support / Resistance (14 Tage)</div>
                <p class="text-white/50 leading-relaxed">
                    Das tiefste Tief (Support) und höchste Hoch (Resistance) der letzten 14 Tage.
                    Ein Kurs nahe am Support mit überverkauftem RSI ist ein starkes Kaufsignal.
                    Ein Kurs nahe der Resistance mit überkauftem RSI spricht eher für Halten oder Verkaufen.
                </p>
            </div>
        </div>
    </div>

    {{-- Modi & Sicherheit --}}
    <div class="glass-card p-8">
        <h2 class="text-lg font-semibold text-white mb-4">Handelsmodi & Sicherheitsmechanismen</h2>
        <div class="space-y-4 text-sm">

            <div class="flex gap-3">
                <span class="badge-paper mt-0.5 shrink-0">PAPER</span>
                <p class="text-white/50 leading-relaxed">
                    Simulierter Handel. Alle Entscheidungen werden aufgezeichnet, aber keine echten Orders auf Coinbase platziert.
                    Ideal zum Testen und Beobachten ohne finanzielles Risiko.
                </p>
            </div>

            <div class="flex gap-3">
                <span class="badge-live mt-0.5 shrink-0">LIVE</span>
                <p class="text-white/50 leading-relaxed">
                    Echter Handel auf Coinbase. Nur aktiv wenn <code class="text-neon-blue bg-white/5 px-1 rounded">TRADING_MODE=live</code>
                    UND <code class="text-neon-blue bg-white/5 px-1 rounded">PAPER_TRADING=false</code> gesetzt sind.
                    Zusätzlich muss der Analyse-Befehl mit <code class="text-neon-blue bg-white/5 px-1 rounded">--live --confirm-live</code> gestartet werden.
                </p>
            </div>

            <div class="mt-4 border-t border-white/[0.06] pt-4">
                <div class="text-white font-medium mb-3">Vor jeder Ausführung werden geprüft:</div>
                <div class="space-y-2">
                    @foreach([
                        ['Entscheidung nicht abgelaufen', 'Gültigkeitsdauer: 30 Minuten (konfigurierbar)'],
                        ['Confidence ≥ Min-Confidence', 'Standard: 60%. Darunter wird die Order verworfen.'],
                        ['Betrag ≤ Max-Trade', 'Standard: €500 pro Trade'],
                        ['Asset in erlaubter Liste', '26 Assets: BTC, ETH, SOL, XRP, DOGE und weitere'],
                        ['Genug Cash (bei Kauf)', 'Cash nach Trade muss ≥ Min-Reserve bleiben (Standard: €200)'],
                        ['Asset im Portfolio (bei Verkauf)', 'Nur verkaufen was wirklich gehalten wird'],
                    ] as [$check, $desc])
                    <div class="flex gap-3 items-start">
                        <svg class="w-4 h-4 text-neon-green shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <span class="text-white/80">{{ $check }}</span>
                            <span class="text-white/40"> — {{ $desc }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Dashboard verstehen --}}
    <div class="glass-card p-8">
        <h2 class="text-lg font-semibold text-white mb-4">Das Dashboard verstehen</h2>
        <div class="space-y-3 text-sm text-white/50">
            <div><span class="text-white/80">Asset Sentiment (24h)</span> — Durchschnittlicher Sentiment-Score aller Artikel der letzten 24h pro Asset. Sortiert nach Score absteigend. Grün = mehr positive News, Rot = mehr negative News.</div>
            <div><span class="text-white/80">Recent Decisions</span> — Die letzten Handelsentscheidungen des Analysers. Bei ausgeschaltetem Auto-Trade können diese hier manuell genehmigt (Execute) oder abgelehnt (Deny) werden.</div>
            <div><span class="text-white/80">Recent Signals</span> — Die letzten Sentiment-Signale aus Artikeln: welches Asset, welche Art (regulatory, market_move etc.), wie stark (-1 bis +1).</div>
            <div><span class="text-white/80">Coinbase Portfolio</span> — Aktueller Stand des Portfolios direkt von Coinbase. Zeigt nur handelbare Spot-Guthaben (kein gestaktes Kapital).</div>
            <div><span class="text-white/80">G/V (Gewinn/Verlust)</span> — Unrealisierter P/L: Differenz zwischen Einstiegspreis und aktuellem Marktpreis, multipliziert mit der gehaltenen Menge.</div>
        </div>
    </div>

    {{-- Tipps --}}
    <div class="glass-card p-8">
        <h2 class="text-lg font-semibold text-white mb-4">Praktische Tipps</h2>
        <div class="space-y-3 text-sm text-white/50 leading-relaxed">
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> Starte immer im <strong class="text-white/70">Paper-Modus</strong> und beobachte mehrere Tage, bevor du auf Live umschaltest.</div>
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> Die <strong class="text-white/70">Min-Reserve</strong> ist dein Sicherheitspuffer – setze sie so, dass du bei einem Notfall noch handlungsfähig bist.</div>
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> <strong class="text-white/70">Mehr Nachrichtenquellen = bessere Signale.</strong> Füge unter "Sources" weitere RSS-Feeds hinzu, besonders für Assets die du aktiv handeln möchtest.</div>
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> Das Bot-Log zeigt dir genau was der Bot denkt. Schau regelmäßig rein, besonders in den ersten Tagen.</div>
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> <strong class="text-white/70">Gemini-Quota:</strong> Das kostenlose Tier ist für ~800 Scoring-Calls täglich ausgelegt. Bei einem großen initialen Import kann die Quota erschöpft sein – sie resetet sich täglich um ~09:00 Uhr MEZ.</div>
            <div class="flex gap-2"><span class="text-neon-blue shrink-0">→</span> Alle Orders gehen über <strong class="text-white/70">EUR-Pairs</strong> (z.B. SOL-EUR). Stelle sicher, dass du auf Coinbase ein EUR-Wallet hast.</div>
        </div>
    </div>

</div>
