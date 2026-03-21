import sys
import csv
import json

def main():
    # PHPからの引数 (CSVパス) を取得
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided"}))
        return

    csv_path = sys.argv[1]
    labels = []
    values = []

    try:
        with open(csv_path, 'r', encoding='utf-8') as f:
            reader = csv.DictReader(f)
            for row in reader:
                # CSVのヘッダーが 'name', 'value' であると想定
                labels.append(row['name'])
                values.append(int(row['value']))
        
        print(json.dumps({"labels": labels, "values": values}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    main()
