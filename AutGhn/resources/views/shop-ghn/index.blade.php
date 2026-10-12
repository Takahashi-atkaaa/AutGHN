<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Danh sach Shop GHN</title>
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
            max-width: 1200px;
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
        .btn {
            padding: 10px 20px;
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
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-group {
            display: flex;
            gap: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        .empty-state p {
            margin-bottom: 15px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .text-muted {
            color: #6c757d;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>Danh sach Shop GHN</h1>
                <a href="{{ route('shop-ghn.create') }}" class="btn btn-primary">+ Them Shop moi</a>
            </div>
            
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if ($shops->isEmpty())
                <div class="empty-state">
                    <p>Chua co shop nao.</p>
                    <a href="{{ route('shop-ghn.create') }}" class="btn btn-primary">Tao shop dau tien</a>
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ten Shop</th>
                            <th>Ma Shop</th>
                            <th>Nguoi gui</th>
                            <th>SDT</th>
                            <th>Dia chi lay hang</th>
                            <th>Trang thai</th>
                            <th>Thao tac</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shops as $shop)
                            <tr>
                                <td>{{ $shop->id }}</td>
                                <td>
                                    <strong>{{ $shop->ten_shop }}</strong>
                                </td>
                                <td>
                                    <code>{{ $shop->ma_shop }}</code>
                                </td>
                                <td>{{ $shop->ten_nguoi_gui ?? '-' }}</td>
                                <td>{{ $shop->so_dien_thoai_nguoi_gui ?? '-' }}</td>
                                <td>
                                    <span class="text-muted" style="max-width: 150px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $shop->dia_chi_lay_hang ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($shop->kich_hoat)
                                        <span class="status-badge status-active">Kich hoat</span>
                                    @else
                                        <span class="status-badge status-inactive">Vo hieu hoa</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('shop-ghn.show', $shop->id) }}" class="btn btn-sm btn-success">Xem</a>
                                        <a href="{{ route('shop-ghn.edit', $shop->id) }}" class="btn btn-sm btn-warning">Sua</a>
                                        <form action="{{ route('shop-ghn.destroy', $shop->id) }}" method="POST" 
                                              onsubmit="return confirm('Ban co chac muon xoa shop nay?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">Xoa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</body>
</html>
