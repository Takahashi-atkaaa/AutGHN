<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiet Shop: {{ $shopGhn->ten_shop }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 24px;
        }
        h1 { color: #333; margin-bottom: 20px; }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-warning { background: #ffc107; color: #212529; }
        .info-group { margin-bottom: 20px; }
        .info-group h3 {
            color: #444;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .info-label {
            width: 180px;
            font-weight: 600;
            color: #666;
        }
        .info-value { flex: 1; color: #333; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1>{{ $shopGhn->ten_shop }}</h1>
                <span class="status-badge {{ $shopGhn->kich_hoat ? 'status-active' : 'status-inactive' }}">
                    {{ $shopGhn->kich_hoat ? 'Kich hoat' : 'Vo hieu hoa' }}
                </span>
            </div>
            
            <div class="info-group">
                <h3>Thong tin co ban</h3>
                <div class="info-row">
                    <div class="info-label">ID</div>
                    <div class="info-value">{{ $shopGhn->id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ma Shop GHN</div>
                    <div class="info-value"><code>{{ $shopGhn->ma_shop }}</code></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ma Bi Mat</div>
                    <div class="info-value"><code>{{ $shopGhn->ma_bi_mat ?? 'Chua co' }}</code></div>
                </div>
            </div>
            
            <div class="info-group">
                <h3>Thong tin nguoi gui</h3>
                <div class="info-row">
                    <div class="info-label">Ten nguoi gui</div>
                    <div class="info-value">{{ $shopGhn->ten_nguoi_gui ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">So dien thoai</div>
                    <div class="info-value">{{ $shopGhn->so_dien_thoai_nguoi_gui ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Dia chi lay hang</div>
                    <div class="info-value">{{ $shopGhn->dia_chi_lay_hang ?? '-' }}</div>
                </div>
            </div>
            
            <div class="info-group">
                <h3>Ma Dia chi</h3>
                <div class="info-row">
                    <div class="info-label">Ma Tinh</div>
                    <div class="info-value">{{ $shopGhn->ma_tinh_lay_hang ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ma Quan</div>
                    <div class="info-value">{{ $shopGhn->ma_quan_lay_hang ?? '-' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Ma Phuong</div>
                    <div class="info-value">{{ $shopGhn->ma_phuong_lay_hang ?? '-' }}</div>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <a href="{{ route('shop-ghn.edit', $shopGhn->id) }}" class="btn btn-warning">Sua</a>
                <a href="{{ route('shop-ghn.index') }}" class="btn btn-secondary">Quay lai</a>
            </div>
        </div>
    </div>
</body>
</html>
