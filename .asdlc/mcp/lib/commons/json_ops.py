import json
import re
from pathlib import Path


def read_file(path: Path) -> dict:
    """Read and parse a JSON file from disk.

    Raises FileNotFoundError if path does not exist.
    Raises json.JSONDecodeError if file content is not valid JSON.
    """
    return json.loads(path.read_text(encoding="utf-8"))


def write_file(path: Path, data: dict) -> None:
    """Serialize data as formatted JSON and write to path.

    Creates all parent directories if they do not exist.
    Uses 2-space indentation and preserves non-ASCII characters.
    """
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False), encoding="utf-8")


def get_value(path: Path, key: str):
    """Read a single value at a dot-notation key from a JSON file.

    Supports dict navigation and array indexing:
        get_value(path, "meta.title")       -> "MyApp"
        get_value(path, "goals[0].item")    -> "Reduce churn"
        get_value(path, "goals")            -> [...]

    Raises KeyError / IndexError if any segment of the path is missing.
    """
    data = read_file(path)
    return _traverse(data, key)


def set_value(path: Path, key: str, value) -> None:
    """Write a single value at a dot-notation key in a JSON file (in-place merge).

    Reads the existing file, sets the value at the given path, then writes back.
    Creates intermediate dicts as needed. Supports array indexing.
    Creates the file from an empty dict if it does not yet exist.

    Example:
        set_value(path, "meta.title", "MyApp")
        set_value(path, "goals[0].item", "Reduce churn")
    """
    data = read_file(path) if path.exists() else {}
    _set_nested(data, key, value)
    write_file(path, data)


def _parse_key(key: str) -> list:
    """Parse a dot-notation key string into a list of string/int parts.

    Examples:
        "meta.title"      -> ["meta", "title"]
        "goals[0].item"   -> ["goals", 0, "item"]
        "platform"        -> ["platform"]

    Raises ValueError if key is empty.
    """
    if not key:
        raise ValueError("key must be a non-empty string")
    parts = []
    for segment in key.split("."):
        m = re.match(r"^(\w[\w-]*)\[(\d+)\]$", segment)
        if m:
            parts.append(m.group(1))
            parts.append(int(m.group(2)))
        else:
            parts.append(segment)
    return parts


def _traverse(data, key: str):
    """Navigate a nested dict/list using parsed key parts and return the value."""
    for part in _parse_key(key):
        data = data[part]
    return data


def _set_nested(data: dict, key: str, value) -> None:
    """Set a value at a nested path within data, creating intermediate dicts as needed."""
    parts = _parse_key(key)
    for part in parts[:-1]:
        if isinstance(part, int):
            data = data[part]
        else:
            data = data.setdefault(part, {})
    data[parts[-1]] = value
