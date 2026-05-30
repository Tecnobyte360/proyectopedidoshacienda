<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe {{ ucfirst($data['rango']['frecuencia']) }} — {{ $tenant->nombre }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 0;">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);overflow:hidden;">

    {{-- HEADER --}}
    <tr><td style="padding:32px 32px 24px;background:linear-gradient(135deg,#d68643 0%,#a85f24 100%);color:#fff;">
        <div style="font-size:11px;letter-spacing:1px;text-transform:uppercase;font-weight:700;opacity:0.85;">Kivox · Informe {{ ucfirst($data['rango']['frecuencia']) }}</div>
        <h1 style="font-size:24px;font-weight:700;margin:8px 0 4px;line-height:1.2;">{{ $tenant->nombre }}</h1>
        <div style="font-size:13px;opacity:0.85;">
            {{ $data['rango']['desde']->format('d M') }} → {{ $data['rango']['hasta']->format('d M Y') }} · {{ $data['rango']['dias'] }} día{{ $data['rango']['dias'] == 1 ? '' : 's' }}
        </div>
    </td></tr>

    {{-- 🧠 ANÁLISIS IA (resumen ejecutivo) --}}
    @php $ia = $data['analisis'] ?? null; @endphp
    @if($ia && ($ia['titular'] || $ia['resumen']))
    <tr><td style="padding:24px 32px 8px;">
        <div style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);border-radius:16px;padding:24px;color:#fff;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:2px;font-weight:700;color:#94a3b8;margin-bottom:8px;">
                Análisis ejecutivo
            </div>
            @if($ia['titular'])
                <div style="font-size:20px;font-weight:700;line-height:1.3;margin-bottom:12px;color:#fff;">
                    {{ $ia['titular'] }}
                </div>
            @endif
            @if($ia['resumen'])
                <div style="font-size:14px;line-height:1.6;color:#cbd5e1;">
                    {{ $ia['resumen'] }}
                </div>
            @endif

            @if(!empty($ia['insights']))
                <div style="margin-top:20px;padding-top:20px;border-top:1px solid #334155;">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:#94a3b8;margin-bottom:10px;">
                        Lo que observamos
                    </div>
                    @foreach($ia['insights'] as $insight)
                        <div style="display:flex;align-items:flex-start;margin:6px 0;font-size:13px;color:#e2e8f0;line-height:1.5;">
                            <span style="color:#fbbf24;margin-right:8px;font-weight:700;">·</span>
                            <span>{{ $insight }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($ia['recomendaciones']))
                <div style="margin-top:16px;padding-top:16px;border-top:1px solid #334155;">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;color:#fbbf24;margin-bottom:10px;">
                        Qué hacer esta semana
                    </div>
                    @foreach($ia['recomendaciones'] as $i => $reco)
                        <div style="display:flex;align-items:flex-start;margin:8px 0;font-size:13px;color:#fef3c7;line-height:1.5;">
                            <span style="background:#fbbf24;color:#0f172a;font-weight:700;font-size:11px;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;flex-shrink:0;">{{ $i + 1 }}</span>
                            <span>{{ $reco }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="margin-top:16px;font-size:10px;color:#64748b;font-style:italic;">
                Análisis generado por IA con base en los datos del período. Las recomendaciones son sugerencias automáticas.
            </div>
        </div>
    </td></tr>
    @endif

    {{-- VOLUMEN --}}
    @if(($inc['volumen'] ?? true) && !empty($data['volumen']))
    @php $v = $data['volumen']; @endphp
    <tr><td style="padding:24px 32px 8px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#64748b;margin-bottom:12px;">📊 Volumen</div>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="33%" style="padding:0 8px 8px 0;">
                    <div style="background:#f1f5f9;border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;">Conversaciones nuevas</div>
                        <div style="font-size:28px;font-weight:700;color:#0f172a;margin-top:4px;">{{ number_format($v['convs_nuevas']) }}</div>
                    </div>
                </td>
                <td width="33%" style="padding:0 4px 8px;">
                    <div style="background:#ecfdf5;border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:#047857;text-transform:uppercase;font-weight:600;">Mensajes del cliente</div>
                        <div style="font-size:28px;font-weight:700;color:#065f46;margin-top:4px;">{{ number_format($v['cliente_msgs']) }}</div>
                    </div>
                </td>
                <td width="33%" style="padding:0 0 8px 8px;">
                    <div style="background:#eff6ff;border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:#1d4ed8;text-transform:uppercase;font-weight:600;">Respondidos</div>
                        <div style="font-size:28px;font-weight:700;color:#1e3a8a;margin-top:4px;">{{ number_format($v['operador_msgs']) }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </td></tr>
    @endif

    {{-- HORAS PICO --}}
    @if(($inc['horas_pico'] ?? true) && !empty($data['horasPico']['pico']['suma']))
    @php $pico = $data['horasPico']['pico']; @endphp
    <tr><td style="padding:16px 32px;">
        <div style="background:#fff7ed;border-left:4px solid #f59e0b;border-radius:8px;padding:16px;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#c2410c;">⏰ Tu ventana pico</div>
            <div style="font-size:18px;font-weight:700;color:#7c2d12;margin-top:6px;">
                {{ $pico['inicio'] }}:00 – {{ $pico['fin'] }}:00 hs
            </div>
            <div style="font-size:13px;color:#92400e;margin-top:4px;">
                {{ number_format($pico['suma']) }} mensajes de clientes en esa ventana ({{ $data['rango']['dias'] }} días).
            </div>
        </div>
    </td></tr>
    @endif

    {{-- TIEMPO DE RESPUESTA --}}
    @if(($inc['tiempo_respuesta'] ?? true) && ($data['tiempoResp']['muestras'] ?? 0) > 0)
    @php $tr = $data['tiempoResp']; @endphp
    <tr><td style="padding:16px 32px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#64748b;margin-bottom:12px;">⏱️ Tiempo de respuesta del operador</div>
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" style="padding-right:8px;">
                    <div style="background:#f1f5f9;border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;">Promedio</div>
                        <div style="font-size:24px;font-weight:700;color:#0f172a;margin-top:4px;">
                            {{ $tr['prom_min'] < 1 ? round($tr['prom_min'] * 60) . 's' : $tr['prom_min'] . ' min' }}
                        </div>
                    </div>
                </td>
                <td width="50%" style="padding-left:8px;">
                    <div style="background:#fef2f2;border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:#b91c1c;text-transform:uppercase;font-weight:600;">Peor caso</div>
                        <div style="font-size:24px;font-weight:700;color:#7f1d1d;margin-top:4px;">
                            {{ $tr['max_min'] < 60 ? $tr['max_min'] . ' min' : round($tr['max_min']/60, 1) . ' h' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        <div style="font-size:12px;color:#64748b;margin-top:8px;">Basado en {{ $tr['muestras'] }} respuestas medidas.</div>
    </td></tr>
    @endif

    {{-- SIN RESPONDER --}}
    @if(($inc['sin_responder'] ?? true) && count($data['sinResponder']))
    <tr><td style="padding:16px 32px;">
        <div style="background:#fef2f2;border-radius:12px;padding:16px;border:1px solid #fecaca;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#b91c1c;">🚨 Atención — conversaciones sin responder</div>
            <div style="font-size:13px;color:#7f1d1d;margin:6px 0 12px;">{{ count($data['sinResponder']) }} cliente{{ count($data['sinResponder']) === 1 ? '' : 's' }} sin respuesta hace +2 horas:</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;">
                @foreach($data['sinResponder'] as $r)
                <tr>
                    <td style="padding:4px 0;color:#7f1d1d;font-family:monospace;">{{ $r->telefono }}</td>
                    <td style="padding:4px 0;color:#7f1d1d;text-align:right;font-weight:600;">
                        hace {{ $r->min_sin_resp < 60 ? $r->min_sin_resp . ' min' : round($r->min_sin_resp/60, 1) . ' h' }}
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
    </td></tr>
    @endif

    {{-- TOP CLIENTES --}}
    @if(($inc['top_clientes'] ?? true) && count($data['topClientes']))
    <tr><td style="padding:16px 32px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#64748b;margin-bottom:12px;">🏆 Tus 5 clientes más activos</div>
        <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
            @foreach($data['topClientes'] as $i => $c)
            <tr style="background:{{ $i % 2 ? '#f8fafc' : '#fff' }};">
                <td style="padding:10px 14px;color:#64748b;font-weight:600;">{{ $i + 1 }}</td>
                <td style="padding:10px 14px;font-family:monospace;color:#0f172a;">{{ $c->telefono }}</td>
                <td style="padding:10px 14px;text-align:right;color:#475569;">{{ $c->total_msgs }} msgs</td>
            </tr>
            @endforeach
        </table>
    </td></tr>
    @endif

    {{-- CAMPAÑAS --}}
    @if(($data['campanas']['total'] ?? 0) > 0)
    @php $c = $data['campanas']; @endphp
    <tr><td style="padding:16px 32px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:#64748b;margin-bottom:12px;">📣 Campañas del período</div>
        <div style="background:#f5f3ff;border-radius:12px;padding:16px;">
            <div style="font-size:14px;color:#5b21b6;">
                <b>{{ $c['total'] }}</b> campaña{{ $c['total'] === 1 ? '' : 's' }} lanzada{{ $c['total'] === 1 ? '' : 's' }} ·
                <b>{{ number_format($c['enviados']) }}</b> entregada{{ $c['enviados'] === 1 ? '' : 's' }}
                @if($c['fallidos'] > 0)
                    · <span style="color:#b91c1c;"><b>{{ $c['fallidos'] }}</b> fallida{{ $c['fallidos'] === 1 ? '' : 's' }}</span>
                @endif
            </div>
        </div>
    </td></tr>
    @endif

    {{-- COSTO META --}}
    @if(($data['costoMeta']['conversaciones'] ?? 0) > 0)
    @php $cm = $data['costoMeta']; @endphp
    <tr><td style="padding:16px 32px;">
        <div style="background:#ecfdf5;border-radius:12px;padding:16px;">
            <div style="font-size:11px;color:#047857;text-transform:uppercase;font-weight:600;">💰 Costo WhatsApp Meta</div>
            <div style="font-size:18px;font-weight:700;color:#065f46;margin-top:4px;">
                ${{ number_format($cm['cop']) }} COP
                <span style="font-size:13px;font-weight:500;color:#10b981;">(USD ${{ $cm['usd'] }})</span>
            </div>
            <div style="font-size:12px;color:#047857;">{{ $cm['conversaciones'] }} conversaciones facturables</div>
        </div>
    </td></tr>
    @endif

    {{-- FOOTER --}}
    <tr><td style="padding:24px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;">
        <div style="font-size:12px;color:#64748b;text-align:center;">
            Generado automáticamente por <b>Kivox</b> · {{ now()->format('d/m/Y H:i') }}
        </div>
        <div style="font-size:11px;color:#94a3b8;text-align:center;margin-top:4px;">
            Para ajustar la frecuencia o destinatarios, entra a tu panel de administración.
        </div>
    </td></tr>

</table>
</td></tr>
</table>

</body>
</html>
