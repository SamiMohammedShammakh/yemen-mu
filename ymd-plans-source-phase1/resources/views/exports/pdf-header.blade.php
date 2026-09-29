<table width="100%" style="border:0;border-bottom:1.5pt solid #2BBB9A;font-family:cairo" dir="rtl">
    <tr>
        <td style="border:0;text-align:right;vertical-align:middle;padding:0 0 4pt 0">
            <div style="font-size:10pt;font-weight:bold;color:#16191c">{{ $h['org'] }}</div>
            <div style="font-size:8pt;color:#555">
                المنصب: <b>{{ $h['position'] }}</b> &nbsp;|&nbsp; السنة: <b>{{ $h['year'] }}</b> &nbsp;|&nbsp; الربع: <b>{{ $h['quarter'] }}</b> &nbsp;|&nbsp; النسخة: <b>{{ $h['version'] }}</b>
                &nbsp;|&nbsp; حالة الخطة: <b style="color:{{ $h['status'] === 'مسودة' ? '#B45309' : '#1E9C80' }}">{{ $h['status'] }}</b> &nbsp;|&nbsp; تاريخ الإصدار: {{ $h['issued_at'] }}
            </div>
        </td>
        <td style="border:0;text-align:left;width:120pt;vertical-align:middle;padding:0 0 4pt 0">
            <img src="{{ resource_path('img/logo-full.png') }}" style="height:32pt">
        </td>
    </tr>
</table>
