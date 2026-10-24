<script>
    window.spigglePackLayout = window.spigglePackLayout || function (items, columns, holdId = null) {
        const widthOf = (item) => Math.max(2, Math.min(columns, Number(item.w) || 2));
        const heightOf = (item) => Math.max(2, Number(item.h) || 2);
        const collides = (a, b) => {
            if (! a || ! b || Number(a.id) === Number(b.id)) {
                return false;
            }

            return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
        };
        const pinned = (item) => {
            if ((Number(item.z) || 0) !== 0) {
                return true;
            }

            return items.some((other) => (Number(other.z) || 0) !== 0 && collides(item, other));
        };

        items.forEach((item) => {
            item.w = widthOf(item);
            item.h = heightOf(item);
            item.x = Math.max(0, Number(item.x) || 0);
            item.y = Math.max(0, Number(item.y) || 0);
            item.z = Number(item.z) || 0;

            if (item.x + item.w > columns) {
                item.x = Math.max(0, columns - item.w);
            }
        });

        const placed = [];
        const sorted = items.slice().sort((a, b) => (a.y - b.y) || (a.x - b.x) || (a.z - b.z) || (Number(a.id) - Number(b.id)));

        sorted.forEach((item) => {
            if (holdId !== null && Number(item.id) === Number(holdId)) {
                return;
            }

            if (pinned(item)) {
                placed.push(item);

                return;
            }

            let y = 0;

            while (y < 250 && placed.some((other) => collides({ ...item, y }, other))) {
                y++;
            }

            item.y = y;
            placed.push(item);
        });

        return items;
    };
</script>
