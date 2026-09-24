<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} - Plánik sedenia</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header .info {
            font-size: 14px;
            color: #666;
        }

        .reservations-table {
            width: 100%;
            margin-bottom: 40px;
            border-collapse: collapse;
        }

        .reservations-table th {
            background-color: #f0f0f0;
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        .reservations-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
        }

        .reservations-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .svg-container {
            margin-top: 40px;
            page-break-before: always;
        }

        .svg-container h2 {
            margin-bottom: 20px;
            text-align: center;
        }

        .svg-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
        }

        .svg-wrapper svg {
            max-width: 100%;
            height: auto;
        }

        .summary {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .summary-item {
            padding: 10px;
        }

        .summary-item .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .summary-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        @media print {
            body {
                padding: 10px;
            }

            .no-print {
                display: none !important;
            }

            .svg-container {
                page-break-before: always;
            }

            @page {
                margin: 1cm;
                size: A4 landscape;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        .print-button:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Tlačiť</button>

    <div class="header">
        <h1>{{ $event->title }}</h1>
        <div class="info">
            <p><strong>Lokalita:</strong> {{ $event->location->address }}</p>
            <p><strong>Dátum:</strong> {{ \Carbon\Carbon::parse($event->start_time)->format('d.m.Y H:i') }}</p>
            <p><strong>Vytlačené:</strong> {{ now()->format('d.m.Y H:i') }}</p>
        </div>
    </div>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Celkom miest</div>
                <div class="value">{{ $event->seats_total }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Rezervované</div>
                <div class="value">{{ $reservations->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Voľné</div>
                <div class="value">{{ $event->seats_total - $reservations->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Obsadenosť</div>
                <div class="value">{{ $event->seats_total > 0 ? round(($reservations->count() / $event->seats_total) * 100) : 0 }}%</div>
            </div>
        </div>
    </div>

    @if($reservations->count() > 0)
        <h2 style="margin-bottom: 15px;">Zoznam rezervácií</h2>
        <table class="reservations-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Sedadlo</th>
                    <th>Meno hosťa</th>
                    <th>Dodatočné informácie</th>
                    <th style="width: 100px;">Stav</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reservations as $reservation)
                    <tr>
                        <td style="text-align: center; font-weight: bold;">{{ $reservation['seat_number'] }}</td>
                        <td>{{ $reservation['guest_name'] }}</td>
                        <td>{{ $reservation['additional_information'] ?? "" }}</td>
                        <td>
                            <span class="status-badge status-{{ $reservation['order_status'] }}">
                                {{ $reservation['order_status'] === 'paid' ? 'Potvrdená' : 'Čaká' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; padding: 40px; color: #666; font-style: italic;">
            Zatiaľ nie sú žiadne rezervácie.
        </p>
    @endif

    @if($event->location->svg_map)
        <div class="svg-container">
            <h2>Plánik sedenia - Mapa</h2>
            <div class="svg-wrapper">
                @php
                    $svgPath = public_path($event->location->svg_map);
                    if (file_exists($svgPath)) {
                        $svgContent = file_get_contents($svgPath);

                        // Parse the SVG and highlight reserved seats
                        $dom = new DOMDocument();
                        @$dom->loadHTML($svgContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                        $svg = $dom->getElementsByTagName('svg')->item(0);

                        if ($svg) {
                            // Find all circles with data-seat attribute
                            $xpath = new DOMXPath($dom);
                            $circles = $xpath->query('//circle[@data-seat]');

                            // Create a map of reserved seats
                            $reservedSeatsMap = [];
                            foreach ($reservations as $res) {
                                $reservedSeatsMap[$res['seat_number']] = $res['guest_name'];
                            }

                            foreach ($circles as $circle) {
                                $seatNumber = (int)$circle->getAttribute('data-seat');

                                if (isset($reservedSeatsMap[$seatNumber])) {
                                    // Reserved seat - red
                                    $circle->setAttribute('fill', '#dc2626');
                                    $circle->setAttribute('stroke', '#991b1b');
                                } else {
                                    // Available seat - green
                                    $circle->setAttribute('fill', '#16a34a');
                                    $circle->setAttribute('stroke', '#15803d');
                                }

                                $circle->setAttribute('stroke-width', '2');
                            }

                            \App\Support\TableLabels::draw($dom, $event->tableSetup());

                            echo $dom->saveHTML();
                        } else {
                            echo '<p style="color: #666;">SVG mapa sa nepodarila načítať.</p>';
                        }
                    } else {
                        echo '<p style="color: #666;">SVG mapa nebola nájdená.</p>';
                    }
                @endphp
            </div>
        </div>
    @endif
</body>
</html>

