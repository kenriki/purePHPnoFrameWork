import sys
import pandas as pd
import json
import io

# Windowsでの出力エンコーディング問題を解決
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

def classify_content(text, custom_rules):
    text = str(text)
    for rule in custom_rules:
        keywords = [k.strip() for k in rule.get('keywords', '').split(',') if k.strip()]
        category = rule.get('category', '未分類')
        if any(k in text for k in keywords):
            return category
    
    # プリセットルール
    presets = {
        "勉強": ["TOEIC", "学習", "本"],
        "開発": ["PHP", "JS", "javascript", "HTML", "CSS", "Python", "API", "Smarty", "vba", "SQL"],
        "顧客課題": ["不具合", "バグ", "要望", "修正"],
        "交通・生活費": ["運賃", "定期", "交通", "バス", "電車"],
        "日記・報告": ["今日", "明日", "した。", "でした", "日記"]
    }
    for cat, ks in presets.items():
        if any(k in text for k in ks): return cat
    return "その他"

def process_csv(file_path, label_col=None, value_col=None, custom_rules_json="[]"):
    try:
        custom_rules = json.loads(custom_rules_json)
        # BOM付きUTF-8やCP932など、複数のエンコーディングを試行
        try:
            df = pd.read_csv(file_path, encoding='utf-8-sig')
        except:
            df = pd.read_csv(file_path, encoding='cp932', errors='replace')

        # 曜日カラムの自動生成
        for col in df.columns:
            if any(k in col.lower() for k in ['日', 'date', 'time', '時']):
                try:
                    dt_series = pd.to_datetime(df[col], errors='coerce')
                    if dt_series.notna().any():
                        df['曜日'] = dt_series.dt.day_name().replace({
                            'Monday':'月','Tuesday':'火','Wednesday':'水','Thursday':'木','Friday':'金','Saturday':'土','Sunday':'日'
                        })
                        break
                except: continue

        # 自動カテゴリの生成
        content_col = next((c for c in df.columns if any(k in c for k in ['内容', 'メモ', 'text', '本文'])), None)
        if content_col:
            df['自動カテゴリ'] = df[content_col].apply(lambda x: classify_content(x, custom_rules))

        raw_data = df.fillna('').to_dict(orient='records')
        all_cols = df.columns.tolist()

        if label_col is None or value_col is None:
            return {"columns": all_cols, "raw_data": raw_data}

        # 集計ロジック
        series = df[value_col].astype(str).str.replace('[,円]', '', regex=True)
        numeric_values = pd.to_numeric(series, errors='coerce')

        if numeric_values.notna().mean() > 0.8:
            df['_val'] = numeric_values.fillna(0)
            summary = df.groupby(label_col)['_val'].sum().reset_index()
            title = f"{value_col} の合計"
        else:
            summary = df.groupby(label_col).size().reset_index(name='_val')
            title = f"{label_col} の件数"

        if label_col == '曜日':
            order = ['月', '火', '水', '木', '金', '土', '日']
            summary[label_col] = pd.Categorical(summary[label_col], categories=order, ordered=True)
            summary = summary.sort_values(label_col)

        return {
            "labels": summary[label_col].astype(str).tolist(),
            "values": summary['_val'].tolist(),
            "title": title,
            "columns": all_cols,
            "raw_data": raw_data
        }
    except Exception as e:
        return {"error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided"}))
        sys.exit(1)
    
    path = sys.argv[1]
    l_col = sys.argv[2] if len(sys.argv) > 2 else None
    v_col = sys.argv[3] if len(sys.argv) > 3 else None
    rules = sys.argv[4] if len(sys.argv) > 4 else "[]"
    
    result = process_csv(path, l_col, v_col, rules)
    print(json.dumps(result, ensure_ascii=False))