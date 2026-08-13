<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; padding: 0; }
    .page {
        position: relative;
        width: {{ $pageWidthPt }}pt;
        height: {{ $pageHeightPt }}pt;
        overflow: hidden;
    }
    .bg {
        position: absolute;
        top: 0;
        left: 0;
        width: {{ $pageWidthPt }}pt;
        height: {{ $pageHeightPt }}pt;
    }
    .line {
        position: absolute;
        white-space: nowrap;
        font-family: "DejaVu Sans", sans-serif;
        line-height: 1;
    }
</style>
</head>
<body>
    <div class="page">
        <img src="{{ $imageUri }}" class="bg">

        @foreach($lines as $line)
            <div class="line" style="left: {{ $line['left'] }}pt; top: {{ $line['top'] }}pt; font-size: {{ $line['fontSize'] }}pt; color: {{ $line['color'] }};">{{ $line['text'] }}</div>
        @endforeach
    </div>
</body>
</html>
