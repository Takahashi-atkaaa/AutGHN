<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sua Shop GHN</title>
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
        .form-group { margin-bottom: 15px; }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        label span.hint {
            font-weight: normal;
            color: #666;
            font-size: 13px;
        }
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input { width: 18px; height: 18px; }
        .checkbox-group label { margin-bottom: 0; }
        .btn-group { display: flex; gap: 10px; margin-top: 20px; }
        .error-message { color: #dc3545; font-size: 13px; margin-top: 5px; }
        .info-box {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 15px 0;
        }
        h2 {
            color: #444;
            font-size: 18px;
            margin: 20px 0 10px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('shop-ghn.index') }}" class="btn btn-secondary" style="margin-bottom: 15px;">&larr; Quay lai</a>
        
        <div class="card">
            <h1>Sua Shop: {{ $shopGhn->ten_shop }}</h1>
            
            @if ($errors->any())
                <div class="info-box">
                    <strong>Co loi xay ra:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <form action="{{ route('shop-ghn.update', $shopGhn->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <h2>Thong tin co ban</h2>
                
                <div class="form-group">
                    <label for="ten_shop">Ten Shop <span style="color: red;">*</span></label>
                    <input type="text" name="ten_shop" id="ten_shop" 
                           value="{{ old('ten_shop', $shopGhn->ten_shop) }}" required>
                    @error('ten_shop')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_shop">Ma Shop GHN <span style="color: red;">*</span></label>
                    <input type="number" name="ma_shop" id="ma_shop" 
                           value="{{ old('ma_shop', $shopGhn->ma_shop) }}" required>
                    @error('ma_shop')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_bi_mat">Ma Bi Mat (Token) <span class="hint">(Optional)</span></label>
                    <input type="text" name="ma_bi_mat" id="ma_bi_mat" 
                           value="{{ old('ma_bi_mat', $shopGhn->ma_bi_mat) }}">
                    @error('ma_bi_mat')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <h2>Thong tin nguoi gui</h2>
                
                <div class="form-group">
                    <label for="ten_nguoi_gui">Ten nguoi gui <span class="hint">(Optional)</span></label>
                    <input type="text" name="ten_nguoi_gui" id="ten_nguoi_gui" 
                           value="{{ old('ten_nguoi_gui', $shopGhn->ten_nguoi_gui) }}">
                </div>
                
                <div class="form-group">
                    <label for="so_dien_thoai_nguoi_gui">So dien thoai nguoi gui <span class="hint">(Optional)</span></label>
                    <input type="text" name="so_dien_thoai_nguoi_gui" id="so_dien_thoai_nguoi_gui" 
                           value="{{ old('so_dien_thoai_nguoi_gui', $shopGhn->so_dien_thoai_nguoi_gui) }}">
                </div>
                
                <div class="form-group">
                    <label for="dia_chi_lay_hang">Dia chi lay hang <span class="hint">(Optional)</span></label>
                    <textarea name="dia_chi_lay_hang" id="dia_chi_lay_hang" rows="3">{{ old('dia_chi_lay_hang', $shopGhn->dia_chi_lay_hang) }}</textarea>
                </div>
                
                <h2>Ma Dia chi</h2>
                
                <div class="form-group">
                    <label for="ma_tinh_lay_hang">Ma Tinh <span class="hint">(Optional)</span></label>
                    <input type="number" name="ma_tinh_lay_hang" id="ma_tinh_lay_hang" 
                           value="{{ old('ma_tinh_lay_hang', $shopGhn->ma_tinh_lay_hang) }}">
                </div>
                
                <div class="form-group">
                    <label for="ma_quan_lay_hang">Ma Quan <span class="hint">(Optional)</span></label>
                    <input type="number" name="ma_quan_lay_hang" id="ma_quan_lay_hang" 
                           value="{{ old('ma_quan_lay_hang', $shopGhn->ma_quan_lay_hang) }}">
                </div>
                
                <div class="form-group">
                    <label for="ma_phuong_lay_hang">Ma Phuong <span class="hint">(Optional)</span></label>
                    <input type="text" name="ma_phuong_lay_hang" id="ma_phuong_lay_hang" 
                           value="{{ old('ma_phuong_lay_hang', $shopGhn->ma_phuong_lay_hang) }}">
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="kich_hoat" id="kich_hoat" value="1" 
                               {{ old('kich_hoat', $shopGhn->kich_hoat) ? 'checked' : '' }}>
                        <label for="kich_hoat">Kich hoat shop</label>
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Luu thay doi</button>
                    <a href="{{ route('shop-ghn.index') }}" class="btn btn-secondary">Huy</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
