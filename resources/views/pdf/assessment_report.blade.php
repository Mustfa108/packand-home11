<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  body {
    font-family: dejavusans, sans-serif;
    direction: rtl;
    text-align: right;
    color: #1a1a1a;
    font-size: 12px;
    line-height: 1.7;
  }

  .header {
    background: #1e3a5f;
    color: #ffffff;
    padding: 18px 16px;
    text-align: center;
    margin-bottom: 18px;
  }
  .header h1 { margin: 0; font-size: 20px; }
  .header p  { margin: 6px 0 0; font-size: 11px; }

  .section {
    margin-bottom: 16px;
    padding: 12px;
    border: 1px solid #e0e0e0;
  }
  .section-title {
    font-size: 14px;
    font-weight: bold;
    color: #1e3a5f;
    border-bottom: 2px solid #1e3a5f;
    padding-bottom: 5px;
    margin-bottom: 10px;
  }

  .score-box {
    text-align: center;
    background: #f0f4f8;
    padding: 14px;
    margin-bottom: 12px;
  }
  .score-number { font-size: 42px; font-weight: bold; color: #1e3a5f; }
  .readiness-badge {
    display: inline-block;
    padding: 4px 14px;
    color: #ffffff;
    font-weight: bold;
    font-size: 13px;
    margin-top: 6px;
  }
  .badge-low    { background: #dc2626; }
  .badge-medium { background: #d97706; }
  .badge-good   { background: #16a34a; }

  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th {
    background: #1e3a5f;
    color: #ffffff;
    padding: 7px;
    text-align: right;
    font-size: 11px;
  }
  td {
    padding: 6px 7px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 11px;
    text-align: right;
  }
  tr:nth-child(even) td { background: #f9fafb; }
  .weak-row td { background: #fff7ed; }

  .phase-block { margin-bottom: 12px; }
  .phase-title { font-weight: bold; color: #374151; margin-bottom: 4px; font-size: 12px; }
  .phase-item  { padding: 6px 9px; background: #f3f4f6; margin-bottom: 4px; font-size: 11px; }
  .kpi-text    { color: #6b7280; font-size: 10px; margin-top: 3px; }

  .footer {
    text-align: center;
    color: #9ca3af;
    font-size: 10px;
    margin-top: 24px;
    border-top: 1px solid #e5e7eb;
    padding-top: 8px;
  }
  .summary-text {
    background: #eff6ff;
    padding: 10px;
    color: #1e40af;
    font-size: 11px;
    line-height: 1.8;
  }
</style>
</head>
<body>

<div class="header">
  <h1>HumaScale — تقرير تقييم الاستعداد للنمو</h1>
  <p>{{ $assessment->user->name }} | {{ $assessment->user->organization_name ?? 'غير محدد' }} | {{ $assessment->created_at->format('Y/m/d') }}</p>
</div>

<div class="section">
  <div class="section-title">النتيجة الإجمالية</div>
  <div class="score-box">
    <div class="score-number">{{ number_format($assessment->overall_score, 1) }}%</div>
    @php
      $level = $assessment->readiness_level;
      $labelAr = match($level) { 'low' => 'منخفض', 'medium' => 'متوسط', 'good' => 'جيد', default => '' };
      $badgeClass = "badge-{$level}";
    @endphp
    <div><span class="readiness-badge {{ $badgeClass }}">مستوى الاستعداد: {{ $labelAr }}</span></div>
  </div>
  @if($assessment->ai_summary_ar)
    <div class="summary-text">{{ $assessment->ai_summary_ar }}</div>
  @endif
</div>

<div class="section">
  <div class="section-title">نتائج المحاور الستة</div>
  <table>
    <thead>
      <tr>
        <th>المحور</th>
        <th>الدرجة الخام</th>
        <th>النسبة المئوية</th>
        <th>المستوى</th>
        <th>ملاحظة</th>
      </tr>
    </thead>
    <tbody>
      @foreach($assessment->pillarResults->sortByDesc('percentage') as $result)
      <tr class="{{ $result->is_weak ? 'weak-row' : '' }}">
        <td>{{ $result->pillar->name_ar }}</td>
        <td>{{ $result->raw_score }} / {{ $result->max_score }}</td>
        <td>{{ number_format($result->percentage, 1) }}%</td>
        <td>
          @if($result->percentage >= 70) جيد
          @elseif($result->percentage >= 40) متوسط
          @else منخفض
          @endif
        </td>
        <td>{{ $result->is_weak ? 'يحتاج تطوير' : 'جيد' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>

@if($assessment->actionPlan)
<div class="section">
  <div class="section-title">خطة التطوير الموجَّهة</div>
  @if($assessment->actionPlan->ai_intro_ar)
    <div class="summary-text" style="margin-bottom:12px;">{{ $assessment->actionPlan->ai_intro_ar }}</div>
  @endif

  @php
    $phases = ['immediate' => 'المرحلة الفورية (0-30 يوم)', 'medium' => 'المرحلة المتوسطة (1-3 أشهر)', 'long' => 'المرحلة البعيدة (3-6 أشهر)'];
  @endphp

  @foreach($phases as $phase => $phaseTitle)
    @php $phaseItems = $assessment->actionPlan->items->where('phase', $phase); @endphp
    @if($phaseItems->count())
    <div class="phase-block">
      <div class="phase-title">{{ $phaseTitle }}</div>
      @foreach($phaseItems as $item)
      <div class="phase-item">
        <strong>{{ $item->pillar->name_ar }}:</strong>
        {{ $item->ai_rephrased_ar ?? $item->action_ar }}
        @if($item->kpi_ar)
          <div class="kpi-text">مؤشر الأداء: {{ $item->kpi_ar }}</div>
        @endif
      </div>
      @endforeach
    </div>
    @endif
  @endforeach
</div>
@endif

<div class="footer">
  تم إنشاء هذا التقرير بواسطة منصة HumaScale | جميع الحقوق محفوظة | {{ now()->format('Y') }}
</div>

</body>
</html>
