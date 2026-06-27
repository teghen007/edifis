<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 0; }
  * { font-family: DejaVu Sans, sans-serif; }
  body { margin: 0; }
  .card {
    width: 320px; height: 200px; margin: 24px auto;
    border: 2px solid #1e3a8a; border-radius: 14px; overflow: hidden;
    position: relative;
  }
  .hdr { background: #1e3a8a; color: #fff; padding: 8px 12px; }
  .hdr table { width: 100%; }
  .school { font-size: 13px; font-weight: bold; }
  .motto { font-size: 7px; letter-spacing: 1.5px; text-transform: uppercase; color: #bfd7fe; }
  .logo { height: 26px; }
  .body { padding: 10px 12px; }
  .photo {
    width: 64px; height: 76px; border: 1px solid #cbd5e1; border-radius: 6px;
    object-fit: cover; background: #f1f5f9;
  }
  .name { font-size: 14px; font-weight: bold; color: #0b1220; }
  .row { font-size: 10px; color: #334155; padding-top: 2px; }
  .row b { color: #1e3a8a; }
  .foot {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: #f1f5f9; color: #64748b; font-size: 8px;
    padding: 4px 12px; text-align: center; letter-spacing: 1px;
  }
  .badge { color: #b45309; font-weight: bold; }
</style>
</head>
<body>
  <div class="card">
    <div class="hdr">
      <table><tr>
        <td>
          <div class="school">{{ $schoolName }}</div>
          @if (!empty($motto))<div class="motto">{{ $motto }}</div>@endif
        </td>
        <td style="text-align:right; width:36px;">
          @if (!empty($logo))<img class="logo" src="{{ $logo }}">@endif
        </td>
      </tr></table>
    </div>

    <div class="body">
      <table><tr>
        <td style="width:72px; vertical-align:top;">
          @if (!empty($photo))
            <img class="photo" src="{{ $photo }}">
          @else
            <div class="photo"></div>
          @endif
        </td>
        <td style="vertical-align:top; padding-left:10px;">
          <div class="name">{{ $student->family_name }} {{ $student->given_name }}</div>
          <div class="row"><b>Class:</b> {{ $className ?? '—' }}</div>
          <div class="row"><b>ID:</b> {{ $student->master_pea_id ?? $student->id }}</div>
          <div class="row"><b>Status:</b> <span class="badge">{{ $student->boarding_status === 'boarding' ? 'BOARDER' : 'DAY' }}</span></div>
          @if ($student->sex)<div class="row"><b>Sex:</b> {{ $student->sex === 'M' ? 'Male' : 'Female' }}</div>@endif
          @if ($student->date_of_birth)<div class="row"><b>DOB:</b> {{ \Illuminate\Support\Carbon::parse($student->date_of_birth)->format('d M Y') }}</div>@endif
        </td>
      </tr></table>
    </div>

    <div class="foot">STUDENT IDENTITY CARD &middot; {{ $year }}</div>
  </div>
</body>
</html>
