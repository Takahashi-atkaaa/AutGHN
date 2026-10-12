<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Them Shop GHN</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        h2 {
            color: #444;
            font-size: 18px;
            margin: 20px 0 10px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .guide {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        .guide h3 {
            color: #007bff;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .guide ul {
            margin-left: 20px;
        }
        .guide li {
            margin: 5px 0;
        }
        .guide a {
            color: #007bff;
            text-decoration: none;
        }
        .guide a:hover {
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        label span.required {
            color: red;
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
            transition: border-color 0.3s;
        }
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        input.error,
        textarea.error {
            border-color: #dc3545;
        }
        .error-message {
            color: #dc3545;
            font-size: 13px;
            margin-top: 5px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-group input {
            width: 18px;
            height: 18px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .btn-back {
            background: #e9ecef;
            color: #495057;
            margin-bottom: 20px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
            color: #c7254e;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        .info-box.warning {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('shop-ghn.index') }}" class="btn btn-back">&larr; Quay lai danh sach</a>
        
        <div class="card">
            <h1>Them Shop GHN moi</h1>
            
            <!-- Huong dan lay Ma Shop -->
            <div class="guide">
                <h3>Cach lay Ma Shop GHN</h3>
                <ol>
                    <li>Dang nhap <a href="https://seller.ghn.vn" target="_blank">GHN Seller</a></li>
                    <li>Vao <strong>Cai dat &gt; Thong tin cua hang</strong></li>
                    <li>Copy <strong>Ma cua hang</strong> (la so, vd: <code>12345</code>)</li>
                </ol>
            </div>
            
            <!-- Huong dan lay Ma Bi Mat -->
            <div class="guide">
                <h3>Cach lay Ma Bi Mat (Token) GHN</h3>
                <ol>
                    <li>Dang nhap <a href="https://seller.ghn.vn" target="_blank">GHN Seller</a></li>
                    <li>Vao <strong>Cai dat &gt; Lay API</strong></li>
                    <li>Copy <strong>Token</strong> (la chuoi ky tu dai)</li>
                </ol>
            </div>
            
            <!-- Huong dan lay Ma Dia Chi -->
            <div class="guide">
                <h3>Cach lay Ma Tinh/Quan/Phuong</h3>
                <ol>
                    <li>Truy cap <a href="https://dev.ghn.vn/site/api-document/master" target="_blank">GHN API Document</a></li>
                    <li>Tim API <strong>Get Province</strong> de lay ma_tinh</li>
                    <li>Tim API <strong>Get District</strong> de lay ma_quan</li>
                    <li>Tim API <strong>Get Ward</strong> de lay ma_phuong</li>
                </ol>
                <p>Hoac goi API truc tiep:</p>
                <ul>
                    <li><code>GET /master-data/province</code> - Lay danh sach tinh</li>
                    <li><code>GET /master-data/district?province_id={id}</code> - Lay danh sach quan</li>
                    <li><code>GET /master-data/ward?district_id={id}</code> - Lay danh sach phuong</li>
                </ul>
            </div>
            
            <!-- Info Box -->
            <div class="info-box">
                <strong>Luu y:</strong> Cac truong danh dau <span class="required">*</span> la bat buoc. 
                Cac truong khac co the de trong neu chua co thong tin.
            </div>
            
            @if ($errors->any())
                <div class="info-box warning">
                    <strong>Co loi xay ra:</strong>
                    <ul style="margin-left: 20px; margin-top: 5px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <form action="{{ route('shop-ghn.store') }}" method="POST">
                @csrf
                
                <h2>Thong tin co ban</h2>
                
                <div class="form-group">
                    <label for="ten_shop">Ten Shop <span class="required">*</span></label>
                    <input type="text" name="ten_shop" id="ten_shop" 
                           value="{{ old('ten_shop') }}" required
                           placeholder="VD: Shop A - Quan 1">
                    @error('ten_shop')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_shop">Ma Shop GHN <span class="required">*</span></label>
                    <input type="number" name="ma_shop" id="ma_shop" 
                           value="{{ old('ma_shop') }}" required
                           placeholder="VD: 12345">
                    @error('ma_shop')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_bi_mat">Ma Bi Mat (Token) <span class="hint">(Optional - lay tu GHN Seller)</span></label>
                    <input type="text" name="ma_bi_mat" id="ma_bi_mat" 
                           value="{{ old('ma_bi_mat') }}"
                           placeholder="Chuoi token dai tu GHN">
                    @error('ma_bi_mat')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <h2>Thong tin nguoi gui</h2>
                
                <div class="form-group">
                    <label for="ten_nguoi_gui">Ten nguoi gui <span class="hint">(Optional)</span></label>
                    <input type="text" name="ten_nguoi_gui" id="ten_nguoi_gui" 
                           value="{{ old('ten_nguoi_gui') }}"
                           placeholder="VD: Nguyen Van A">
                    @error('ten_nguoi_gui')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="so_dien_thoai_nguoi_gui">So dien thoai nguoi gui <span class="hint">(Optional)</span></label>
                    <input type="text" name="so_dien_thoai_nguoi_gui" id="so_dien_thoai_nguoi_gui" 
                           value="{{ old('so_dien_thoai_nguoi_gui') }}"
                           placeholder="VD: 0909123456">
                    @error('so_dien_thoai_nguoi_gui')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="dia_chi_lay_hang">Dia chi lay hang <span class="hint">(Optional)</span></label>
                    <textarea name="dia_chi_lay_hang" id="dia_chi_lay_hang" rows="3"
                              placeholder="VD: 123 Nguyen Trai, Phuong 5, Quan 1, TP Ho Chi Minh">{{ old('dia_chi_lay_hang') }}</textarea>
                    @error('dia_chi_lay_hang')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <h2>Ma Dia chi (Tinh/Quan/Phuong)</h2>
                
                <div class="info-box">
                    Cac ma nay can de tinh phi ship chinh xac. Neu khong co, phi ship se khong duoc tinh.
                </div>
                
                <div class="form-group">
                    <label for="ma_tinh_lay_hang">Ma Tinh <span class="hint">(Optional)</span></label>
                    <input type="number" name="ma_tinh_lay_hang" id="ma_tinh_lay_hang" 
                           value="{{ old('ma_tinh_lay_hang') }}"
                           placeholder="VD: 201 (TP Ho Chi Minh)">
                    @error('ma_tinh_lay_hang')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_quan_lay_hang">Ma Quan <span class="hint">(Optional)</span></label>
                    <input type="number" name="ma_quan_lay_hang" id="ma_quan_lay_hang" 
                           value="{{ old('ma_quan_lay_hang') }}"
                           placeholder="VD: 1520 (Quan 1)">
                    @error('ma_quan_lay_hang')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="ma_phuong_lay_hang">Ma Phuong <span class="hint">(Optional)</span></label>
                    <input type="text" name="ma_phuong_lay_hang" id="ma_phuong_lay_hang" 
                           value="{{ old('ma_phuong_lay_hang') }}"
                           placeholder="VD: 20808">
                    @error('ma_phuong_lay_hang')
                        <div class="error-message">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="kich_hoat" id="kich_hoat" value="1" 
                               {{ old('kich_hoat', true) ? 'checked' : '' }}>
                        <label for="kich_hoat" style="margin-bottom: 0;">Kich hoat shop</label>
                    </div>
                    <small style="color: #666;">Shop khong kich hoat se khong hien thi khi chon tao don</small>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Tao Shop</button>
                    <a href="{{ route('shop-ghn.index') }}" class="btn btn-secondary">Huy</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
